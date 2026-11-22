<?php

namespace App;

use App\Models\GalleryPhoto;
use Aws\Exception\AwsException;
use Aws\Exception\CredentialsException;
use Aws\Rekognition\RekognitionClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class RekognitionGallery
{
    public function __construct(private RekognitionClient $client) {}

    public function query(): Builder
    {
        return GalleryPhoto::query()->where('region', config('rekognition.region'))->where('collection', config('rekognition.collection'));
    }

    public function upload(UploadedFile $file): GalleryPhoto
    {
        $path = $file->store('gallery', 'local');
        if (! $path) {
            throw new RuntimeException('Não foi possível salvar o arquivo.');
        }
        try {
            $photo = GalleryPhoto::create([
                'original_name' => $file->getClientOriginalName(),
                'path' => $path,
                'region' => config('rekognition.region'),
                'collection' => config('rekognition.collection'),
                'status' => 'processing',
            ]);
        } catch (\Throwable $exception) {
            Storage::disk('local')->delete($path);
            throw $exception;
        }

        return $this->index($photo);
    }

    public function index(GalleryPhoto $photo): GalleryPhoto
    {

        try {

            try {
                $this->client->createCollection(['CollectionId' => $photo->collection]);
            } catch (AwsException $exception) {
                if ($exception->getAwsErrorCode() !== 'ResourceAlreadyExistsException') {
                    throw $exception;
                }
            }

            $result = $this->client->indexFaces([
                'CollectionId' => $photo->collection,
                'ExternalImageId' => (string) $photo->id,
                'Image' => ['Bytes' => Storage::disk('local')->get($photo->path)],
                'QualityFilter' => 'AUTO',
                'DetectionAttributes' => ['DEFAULT'],
            ]);

            $faces = array_column(array_column($result->get('FaceRecords') ?? [], 'Face'), 'FaceId');
            $photo->update([
                'status' => $faces === [] ? 'no_faces' : 'indexed',
                'face_ids' => $faces,
                'unindexed_faces' => count($result->get('UnindexedFaces') ?? []),
                'error' => null,
            ]);

        } catch (AwsException|CredentialsException $exception) {
            $photo->update(['status' => 'failed', 'error' => $this->errorMessage($exception)]);
        }

        return $photo;
    }

    public function search(UploadedFile $selfie): array
    {
        if (! $this->query()->where('status', 'indexed')->exists()) {
            throw ValidationException::withMessages(['selfie' => 'Cadastre pelo menos uma foto com rostos indexados antes de buscar.']);
        }
        $bytes = $selfie->getContent();
        $detected = $this->client->detectFaces(['Image' => ['Bytes' => $bytes], 'Attributes' => ['DEFAULT']]);
        if (count($detected->get('FaceDetails') ?? []) !== 1) {
            throw ValidationException::withMessages(['selfie' => 'Use uma selfie com exatamente um rosto nítido.']);
        }
        $result = $this->client->searchFacesByImage([
            'CollectionId' => config('rekognition.collection'),
            'Image' => ['Bytes' => $bytes],
            'FaceMatchThreshold' => config('rekognition.threshold'),
            'MaxFaces' => 4096,
        ]);
        $scores = [];
        foreach ($result->get('FaceMatches') ?? [] as $match) {
            $id = (string) ($match['Face']['ExternalImageId'] ?? '');
            if (! preg_match('/^[1-9][0-9]*$/D', $id)) {
                continue;
            }
            $scores[$id] = max($scores[$id] ?? 0, $match['Similarity']);
        }
        $photos = $this->query()->where('status', 'indexed')->whereIn('id', array_keys($scores))->get();

        return [
            'photos' => $photos->map(fn (GalleryPhoto $photo) => [...$this->present($photo), 'similarity' => round($scores[$photo->id], 2)])->sortByDesc('similarity')->values()->all(),
            'limit_reached' => count($result->get('FaceMatches') ?? []) >= 4096,
        ];
    }

    public function present(GalleryPhoto $photo): array
    {
        return [
            'id' => $photo->id,
            'name' => $photo->original_name,
            'url' => route('gallery.photo', $photo->id),
            'status' => $photo->status,
            'faces' => count($photo->face_ids ?? []),
            'unindexed_faces' => $photo->unindexed_faces,
            'error' => $photo->error,
            'retry_url' => route('gallery.retry', $photo->id),
        ];
    }

    public function errorMessage(AwsException|CredentialsException $exception): string
    {
        if ($exception instanceof CredentialsException) {
            return 'Sessão AWS indisponível. Execute aws login no computador do Laravel e tente novamente.';
        }

        return match ($exception->getAwsErrorCode()) {
            'ExpiredTokenException', 'ExpiredToken', 'UnrecognizedClientException' => 'Sessão AWS expirada ou inválida. Execute aws login e tente novamente.',
            'AccessDeniedException' => 'Seu acesso AWS não tem permissão para esta operação no Rekognition.',
            'InvalidParameterException', 'InvalidImageFormatException', 'ImageTooLargeException' => 'Imagem não aceita pelo Rekognition. Use JPG ou PNG de até 5 MB, com rosto nítido.',
            'ResourceNotFoundException' => 'Coleção não encontrada. Confira a região e o nome configurados.',
            default => 'Falha ao acessar o Rekognition. Confira a conexão, os certificados do PHP e a sessão AWS.',
        };
    }
}

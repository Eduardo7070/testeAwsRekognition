<?php

namespace App\Http\Controllers;

use App\Http\Requests\SearchGalleryRequest;
use App\Http\Requests\UploadGalleryRequest;
use App\Models\GalleryPhoto;
use App\RekognitionGallery;
use Aws\Exception\AwsException;
use Aws\Exception\CredentialsException;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GalleryController extends Controller
{
    public function index(RekognitionGallery $gallery): View
    {
        return view('gallery', ['photos' => $gallery->query()->latest()->get()->map(fn (GalleryPhoto $photo) => $gallery->present($photo))]);
    }

    public function upload(UploadGalleryRequest $request, RekognitionGallery $gallery): JsonResponse
    {
        $photos = [];
        foreach ($request->file('photos') as $file) {
            $photos[] = $gallery->present($gallery->upload($file));
        }

        return response()->json(['photos' => $photos], 201);
    }

    public function search(SearchGalleryRequest $request, RekognitionGallery $gallery): JsonResponse
    {

        try {
            return response()->json($gallery->search($request->file('selfie')));
        } catch (AwsException|CredentialsException $exception) {
            return response()->json(['message' => $gallery->errorMessage($exception)], 503);
        }

    }

    public function photo(string $id, RekognitionGallery $gallery): StreamedResponse
    {
        $photo = $gallery->query()->findOrFail($id);
        abort_unless(Storage::disk('local')->exists($photo->path), 404);

        return Storage::disk('local')->response($photo->path, null, ['Cache-Control' => 'private, no-store']);
    }

    public function retry(string $id, RekognitionGallery $gallery): JsonResponse
    {
        $photo = $gallery->query()->findOrFail($id);
        abort_unless($gallery->query()->whereKey($id)->where('status', 'failed')->update(['status' => 'processing', 'error' => null]), 409, 'Esta foto não está disponível para reprocessamento.');

        return response()->json(['photo' => $gallery->present($gallery->index($photo->refresh()))]);
    }
}

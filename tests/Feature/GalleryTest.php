<?php

namespace Tests\Feature;

use App\Models\GalleryPhoto;
use Aws\Command;
use Aws\Exception\CredentialsException;
use Aws\Rekognition\Exception\RekognitionException;
use Aws\Rekognition\RekognitionClient;
use Aws\Result;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

class GalleryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['rekognition.collection' => 'campeonato-teste', 'rekognition.region' => 'us-east-2']);
    }

    private function image(): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('foto.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
    }

    private function client(): MockInterface
    {
        $client = Mockery::mock(RekognitionClient::class);
        $this->app->instance(RekognitionClient::class, $client);

        return $client;
    }

    public function test_upload_persists_file_and_face_mapping_in_database(): void
    {
        $client = $this->client();
        $client->shouldReceive('createCollection')->once()->andThrow(new RekognitionException('exists', new Command('CreateCollection'), ['code' => 'ResourceAlreadyExistsException']));
        $client->shouldReceive('indexFaces')->once()->withArgs(fn ($args) => is_string($args['ExternalImageId']) && GalleryPhoto::whereKey($args['ExternalImageId'])->exists() && $args['CollectionId'] === 'campeonato-teste' && strlen($args['Image']['Bytes']) > 0)->andReturn(new Result(['FaceRecords' => [['Face' => ['FaceId' => 'face-1']]], 'UnindexedFaces' => [[]]]));
        $this->postJson('/gallery', ['photos' => [$this->image()]])->assertCreated()->assertJsonPath('photos.0.status', 'indexed')->assertJsonPath('photos.0.faces', 1)->assertJsonPath('photos.0.unindexed_faces', 1);
        $photo = GalleryPhoto::firstOrFail();
        $this->assertSame(['face-1'], $photo->face_ids);
        Storage::disk('local')->assertExists($photo->path);
        $this->get('/gallery/'.$photo->id.'/photo')->assertOk()->assertHeader('content-type', 'image/png');
    }

    public function test_gallery_uses_incrementing_integer_ids_and_rejects_uuid_routes(): void
    {
        $first = GalleryPhoto::factory()->create();
        $second = GalleryPhoto::factory()->create();
        $this->assertIsInt($first->id);
        $this->assertSame($first->id + 1, $second->id);
        $this->get('/gallery/00000000-0000-4000-8000-000000000000/photo')->assertNotFound();
    }

    public function test_batch_accepts_ten_photos(): void
    {
        $client = $this->client();
        $client->shouldReceive('createCollection')->times(10)->andReturn(new Result);
        $client->shouldReceive('indexFaces')->times(10)->andReturn(new Result(['FaceRecords' => [['Face' => ['FaceId' => 'face-1']]]]));
        $files = array_map(fn () => $this->image(), range(1, 10));
        $this->postJson('/gallery', ['photos' => $files])->assertCreated()->assertJsonCount(10, 'photos');
        $this->assertDatabaseCount('gallery', 10);
        $this->assertCount(10, Storage::disk('local')->allFiles());
    }

    public function test_rejects_eleven_photos_invalid_type_and_oversized_image(): void
    {
        $this->client();
        $this->postJson('/gallery', ['photos' => array_map(fn () => $this->image(), range(1, 11))])->assertUnprocessable()->assertJsonValidationErrors('photos');
        $this->postJson('/gallery', ['photos' => [UploadedFile::fake()->create('doc.pdf', 10)]])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->postJson('/gallery', ['photos' => [$this->image()->size(5121)]])->assertUnprocessable()->assertJsonValidationErrors('photos.0');
        $this->assertDatabaseCount('gallery', 0);
    }

    public function test_no_faces_photo_is_kept_but_excluded_from_search(): void
    {
        $client = $this->client();
        $client->shouldReceive('createCollection')->once()->andReturn(new Result);
        $client->shouldReceive('indexFaces')->once()->andReturn(new Result(['FaceRecords' => []]));
        $this->postJson('/gallery', ['photos' => [$this->image()]])->assertCreated()->assertJsonPath('photos.0.status', 'no_faces');
        Storage::disk('local')->assertExists(GalleryPhoto::first()->path);
        $this->postJson('/gallery/search', ['selfie' => $this->image(), 'consent' => 1])->assertUnprocessable();
    }

    public function test_failed_aws_upload_is_retained_and_can_be_retried_without_duplicate_rows(): void
    {
        $client = $this->client();
        $client->shouldReceive('createCollection')->once()->andThrow(new CredentialsException('SECRET INTERNAL DETAILS'));
        $this->postJson('/gallery', ['photos' => [$this->image()]])->assertCreated()->assertJsonPath('photos.0.status', 'failed')->assertDontSee('SECRET INTERNAL DETAILS');
        $photo = GalleryPhoto::firstOrFail();
        $client->shouldReceive('createCollection')->once()->andReturn(new Result);
        $client->shouldReceive('indexFaces')->once()->andReturn(new Result(['FaceRecords' => [['Face' => ['FaceId' => 'face-2']]]]));
        $this->postJson('/gallery/'.$photo->id.'/retry')->assertOk()->assertJsonPath('photo.status', 'indexed');
        $this->assertDatabaseCount('gallery', 1);
        $this->postJson('/gallery/'.$photo->id.'/retry')->assertConflict();
    }

    public function test_search_returns_only_matching_database_photos_without_duplicates(): void
    {
        $first = GalleryPhoto::factory()->create();
        $second = GalleryPhoto::factory()->create();
        $other = GalleryPhoto::factory()->create();
        $differentCollection = GalleryPhoto::factory()->create(['collection' => 'outro-campeonato']);
        $failed = GalleryPhoto::factory()->create(['status' => 'failed']);
        $client = $this->client();
        $client->shouldReceive('detectFaces')->once()->andReturn(new Result(['FaceDetails' => [[]]]));
        $client->shouldReceive('searchFacesByImage')->once()->withArgs(fn ($args) => $args['CollectionId'] === 'campeonato-teste' && $args['MaxFaces'] === 4096 && $args['FaceMatchThreshold'] === 95.0)->andReturn(new Result(['FaceMatches' => [
            ['Face' => ['ExternalImageId' => (string) $first->id], 'Similarity' => 98.1],
            ['Face' => ['ExternalImageId' => (string) $second->id], 'Similarity' => 99.5],
            ['Face' => ['ExternalImageId' => (string) $first->id], 'Similarity' => 99.1],
            ['Face' => ['ExternalImageId' => $differentCollection->id], 'Similarity' => 99.9],
            ['Face' => ['ExternalImageId' => $failed->id], 'Similarity' => 99.9],
            ['Face' => ['ExternalImageId' => $other->id.'1234567-1111-4111-8111-111111111111'], 'Similarity' => 99.9],
        ]]));
        $this->postJson('/gallery/search', ['selfie' => $this->image(), 'consent' => 1])
            ->assertOk()->assertJsonCount(2, 'photos')->assertJsonPath('photos.0.id', $second->id)
            ->assertJsonPath('photos.1.id', $first->id)->assertJsonPath('photos.1.similarity', 99.1)->assertJsonMissing(['id' => $other->id]);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('gallery', 5);
    }

    public function test_selfie_requires_consent_and_exactly_one_face(): void
    {
        GalleryPhoto::factory()->create();
        $client = $this->client();
        $this->postJson('/gallery/search', ['selfie' => $this->image()])->assertUnprocessable()->assertJsonValidationErrors('consent');
        $client->shouldReceive('detectFaces')->twice()->andReturn(new Result(['FaceDetails' => []]), new Result(['FaceDetails' => [[], []]]));
        foreach (range(1, 2) as $attempt) {
            $this->postJson('/gallery/search', ['selfie' => $this->image(), 'consent' => 1])->assertUnprocessable()->assertJsonValidationErrors('selfie');
        }
    }

    public function test_search_handles_no_matches_and_expired_credentials(): void
    {
        GalleryPhoto::factory()->create();
        $client = $this->client();
        $client->shouldReceive('detectFaces')->once()->andReturn(new Result(['FaceDetails' => [[]]]));
        $client->shouldReceive('searchFacesByImage')->once()->andReturn(new Result(['FaceMatches' => []]));
        $this->postJson('/gallery/search', ['selfie' => $this->image(), 'consent' => 1])->assertOk()->assertJsonCount(0, 'photos');
        $client->shouldReceive('detectFaces')->once()->andThrow(new CredentialsException('secret'));
        $this->postJson('/gallery/search', ['selfie' => $this->image(), 'consent' => 1])->assertStatus(503)->assertSee('aws login')->assertDontSee('secret');
    }

    public function test_failed_photo_does_not_abort_other_photos_in_batch(): void
    {
        $client = $this->client();
        $client->shouldReceive('createCollection')->twice()->andReturn(new Result);
        $client->shouldReceive('indexFaces')->once()->andThrow(new RekognitionException('denied', new Command('IndexFaces'), ['code' => 'AccessDeniedException']));
        $client->shouldReceive('indexFaces')->once()->andReturn(new Result(['FaceRecords' => [['Face' => ['FaceId' => 'ok']]]]));
        $this->postJson('/gallery', ['photos' => [$this->image(), $this->image()]])->assertCreated()->assertJsonPath('photos.0.status', 'failed')->assertJsonPath('photos.1.status', 'indexed');
    }

    public function test_gallery_is_local_only_and_does_not_serve_other_collections(): void
    {
        $foreign = GalleryPhoto::factory()->create(['collection' => 'outra']);
        $this->get('/gallery/'.$foreign->id.'/photo')->assertNotFound();
        $this->app->instance('env', 'production');
        $this->get('/')->assertForbidden();
        $this->get('/gallery/'.$foreign->id.'/photo')->assertForbidden();
    }
}

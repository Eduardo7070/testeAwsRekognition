<?php

namespace Database\Factories;

use App\Models\GalleryPhoto;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GalleryPhoto> */
class GalleryPhotoFactory extends Factory
{
    public function definition(): array
    {
        return [
            'original_name' => 'foto.jpg',
            'path' => 'gallery/'.fake()->uuid().'.jpg',
            'region' => config('rekognition.region'),
            'collection' => config('rekognition.collection'),
            'status' => 'indexed',
            'face_ids' => [fake()->uuid()],
            'unindexed_faces' => 0,
        ];
    }
}

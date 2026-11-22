<?php

namespace App\Models;

use Database\Factories\GalleryPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['original_name', 'path', 'region', 'collection', 'status', 'face_ids', 'unindexed_faces', 'error'])]
class GalleryPhoto extends Model
{
    /** @use HasFactory<GalleryPhotoFactory> */
    use HasFactory;

    protected $table = 'gallery';

    protected function casts(): array
    {
        return ['face_ids' => 'array', 'unindexed_faces' => 'integer'];
    }
}

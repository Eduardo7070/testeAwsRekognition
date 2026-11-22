<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gallery', function (Blueprint $table) {
            $table->id();
            $table->text('original_name');
            $table->string('path');
            $table->string('region', 30);
            $table->string('collection', 255);
            $table->string('status', 20)->default('processing')->index();
            $table->json('face_ids')->nullable();
            $table->unsignedInteger('unindexed_faces')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery');
    }
};

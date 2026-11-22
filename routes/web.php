<?php

use App\Http\Controllers\GalleryController;
use App\Http\Middleware\LocalGalleryOnly;
use Illuminate\Support\Facades\Route;

Route::middleware(LocalGalleryOnly::class)->group(function () {
    Route::get('/', [GalleryController::class, 'index'])->name('gallery.index');
    Route::post('/gallery', [GalleryController::class, 'upload'])->middleware('throttle:60,1')->name('gallery.upload');
    Route::post('/gallery/search', [GalleryController::class, 'search'])->middleware('throttle:20,1')->name('gallery.search');
    Route::post('/gallery/{id}/retry', [GalleryController::class, 'retry'])->whereNumber('id')->middleware('throttle:20,1')->name('gallery.retry');
    Route::get('/gallery/{id}/photo', [GalleryController::class, 'photo'])->whereNumber('id')->name('gallery.photo');
});

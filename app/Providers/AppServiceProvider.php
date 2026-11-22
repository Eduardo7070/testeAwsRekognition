<?php

namespace App\Providers;

use Aws\Rekognition\RekognitionClient;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RekognitionClient::class, fn () => new RekognitionClient([
            'version' => 'latest',
            'region' => config('rekognition.region'),
            'http' => ['connect_timeout' => 5, 'timeout' => 20],
            'retries' => 0,
        ]));
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
    }
}

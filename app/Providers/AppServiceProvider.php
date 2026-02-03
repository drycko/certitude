<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;
use App\Filesystem\UniformGCSAdapter;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register GCS custom driver
        $this->registerGCSDriver();
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 
    }
    
    // Register GCS custom driver
    protected function registerGCSDriver()
    {
        Storage::extend('gcs', function ($app, $config) {
            $adapter = new UniformGCSAdapter($config);
            $filesystem = new Filesystem($adapter, $config);
            return new \Illuminate\Filesystem\FilesystemAdapter($filesystem, $adapter, $config);
        });
    }
}

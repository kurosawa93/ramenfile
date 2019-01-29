<?php

namespace Ordent\RamenFile\Providers;

use Illuminate\Support\ServiceProvider;

class RamenFileProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        // adding default routes
        $this->loadRoutesFrom(__DIR__.'/../Routes/routes.php');
        // adding migration for file model type
        $this->loadMigrationsFrom(__DIR__.'/../Migrations');
        
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        // register ramen rest
        $this->app->register(Ordent\RamenRest\Providers\RamenRestProvider::class);
        // merge config for default package config and setting up disks type filesystem
        $this->mergeConfigFrom(__DIR__.'/../config/filesystems-disks.php', 'filesystems.disks');
        // adding support to stores files in google cloud storage
        $this->app->register(\Superbalist\LaravelGoogleCloudStorage\GoogleCloudStorageServiceProvider::class);
        // adding support for image manipulation
        $this->app->register(\Intervention\Image\ImageServiceProvider::class);
        // adding support for files processor
        $this->app->singleton('FileProcessor', function($app){
            return new \Ordent\RamenFile\Processor\FileProcessor;
        });
    }
}

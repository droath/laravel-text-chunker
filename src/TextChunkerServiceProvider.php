<?php

declare(strict_types=1);

namespace Droath\TextChunker;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class TextChunkerServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('text-chunker')
            ->hasConfigFile();
    }

    /**
     * Register package services.
     */
    public function packageRegistered(): void
    {
        // Register TextChunkerManager as a singleton
        $this->app->singleton('text-chunker', function ($app) {
            return new TextChunkerManager();
        });
    }

    /**
     * Bootstrap package services.
     */
    public function packageBooted(): void
    {
        // Auto-register custom strategies from config
        $this->registerCustomStrategies();
    }

    /**
     * Register custom strategies from configuration.
     */
    protected function registerCustomStrategies(): void
    {
        $customStrategies = config('text-chunker.custom_strategies', []);

        if (! is_array($customStrategies)) {
            return;
        }

        $manager = $this->app->make('text-chunker');

        foreach ($customStrategies as $name => $class) {
            if (is_string($name) && is_string($class) && class_exists($class)) {
                $manager->extend($name, $class);
            }
        }
    }
}

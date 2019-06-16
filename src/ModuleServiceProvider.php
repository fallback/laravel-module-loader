<?php

declare(strict_types=1);

namespace SebastiaanLuca\Module;

use Illuminate\Support\ServiceProvider;
use SebastiaanLuca\Module\Commands\Cache;
use SebastiaanLuca\Module\Commands\ClearCache;
use SebastiaanLuca\Module\Commands\CreateModule;
use SebastiaanLuca\Module\Commands\RefreshModules;
use SebastiaanLuca\Module\Commands\RegisterModuleAutoloading;
use SebastiaanLuca\Module\Services\ModuleLoader;

class ModuleServiceProvider extends ServiceProvider
{
    /**
     * Register the application services.
     */
    public function register() : void
    {
        $this->configure();

        $this->app->singleton(ModuleLoader::class, function () {
            return new ModuleLoader(
                config($this->getShortPackageName())
            );
        });

        $this->app->bind($this->getShortPackageName(), ModuleLoader::class);

        $this->commands([
            CreateModule::class,
            RegisterModuleAutoloading::class,
            RefreshModules::class,
            Cache::class,
            ClearCache::class,
        ]);

        app(ModuleLoader::class)->load();
    }

    /**
     * Bootstrap the application services.
     */
    public function boot() : void
    {
        $this->registerPublishableResources();
    }

    /**
     * Register the package configuration.
     */
    private function configure() : void
    {
        $this->mergeConfigFrom(
            $this->getConfigurationPath(),
            $this->getShortPackageName()
        );
    }

    /**
     * @return void
     */
    private function registerPublishableResources() : void
    {
        $this->publishes([
            $this->getConfigurationPath() => config_path($this->getShortPackageName() . '.php'),
        ], $this->getPackageName() . ' (configuration)');
    }

    /**
     * @return string
     */
    private function getConfigurationPath() : string
    {
        return __DIR__ . '/../config/module-loader.php';
    }

    /**
     * @return string
     */
    private function getShortPackageName() : string
    {
        return 'module-loader';
    }

    /**
     * @return string
     */
    private function getPackageName() : string
    {
        return 'laravel-' . $this->getShortPackageName();
    }
}

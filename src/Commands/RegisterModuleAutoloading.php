<?php

declare(strict_types=1);

namespace SebastiaanLuca\Module\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use SebastiaanLuca\Module\Exceptions\JsonException;
use SebastiaanLuca\Module\Services\ModuleLoader;

class RegisterModuleAutoloading extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:autoload
                            {--K|keep : Keep existing module autoload entries}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan your project for modules and write their autoload config to composer.json';

    /**
     * @var \SebastiaanLuca\Module\Services\ModuleLoader
     */
    private $modules;

    /**
     * Create a new command instance.
     *
     * @param \SebastiaanLuca\Module\Services\ModuleLoader $modules
     */
    public function __construct(ModuleLoader $modules)
    {
        parent::__construct();

        $this->modules = $modules;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() : void
    {
        $modules = $this->modules->getModules();

        $this->writeAutoloadConfig(
            $this->getAutoloadConfig($modules)
        );

        $this->info(sprintf(
            'Wrote composer.json autoload configuration for %s modules',
            count($modules)
        ));
    }

    /**
     * @param array $modules
     *
     * @return array
     */
    private function getAutoloadConfig(array $modules) : array
    {
        $classmap = [];
        $psr4 = [];
        $psr4Dev = [];

        /** @var \SebastiaanLuca\Module\Entities\Module $module */
        foreach ($modules as $module) {
            $psrName = $module->namespace . '\\';

            $psr4 = array_merge($psr4, [$psrName => $module->relativePath . '/src/']);

            if (is_dir($tests = $module->absolutePath . '/tests')) {
                $psr4Dev = array_merge($psr4Dev, [$psrName . 'Tests\\' => $module->relativePath . '/tests/']);
            }

            if (is_dir($migrations = $module->absolutePath . '/database/migrations')) {
                $classmap[] = $module->relativePath . '/database/migrations';
            }

            if (is_dir($seeders = $module->absolutePath . '/database/seeds')) {
                $classmap[] = $module->relativePath . '/database/seeds';
            }

            if (is_dir($factories = $module->absolutePath . '/database/factories')) {
                $classmap[] = $module->relativePath . '/database/factories';
            }
        }

        return compact(
            'classmap',
            'psr4',
            'psr4Dev'
        );
    }

    /**
     * @param array $autoloadConfig
     *
     * @throws \SebastiaanLuca\Module\Exceptions\JsonException
     */
    private function writeAutoloadConfig(array $autoloadConfig) : void
    {
        [
            'classmap' => $classmap,
            'psr4' => $psr4,
            'psr4Dev' => $psr4Dev,
        ] = $autoloadConfig;

        $composerPath = base_path('composer.json');

        if (file_exists($composerPath)) {
            $config = json_decode(file_get_contents($composerPath), true, 512, JSON_OBJECT_AS_ARRAY | JSON_UNESCAPED_SLASHES);
        }
        else {
            $config = [];
        }

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw JsonException::invalidJson(json_last_error_msg());
        }

        $this->mergeConfigValue($config, 'autoload.classmap', $classmap);
        $this->mergeConfigValue($config, 'autoload.psr-4', $psr4);
        $this->mergeConfigValue($config, 'autoload-dev.psr-4', $psr4Dev);

        $config = json_encode($config, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        file_put_contents($composerPath, $config . PHP_EOL);
    }

    /**
     * @param array $config
     * @param string $key
     * @param array $value
     */
    private function setConfigValue(array &$config, string $key, array $value) : void
    {
        Arr::set($config, $key, $value);
    }

    /**
     * @param array $config
     * @param string $key
     * @param array $value
     */
    private function mergeConfigValue(array &$config, string $key, array $value) : void
    {
        $existing = Arr::get($config, $key, []);

        if (! $this->option('keep')) {
            $existing = collect($existing)
                ->reject(function ($directory, $name) {
                    return Str::startsWith($directory, config('module-loader.directories'));
                })
                ->toArray();
        }

        $value = array_unique(array_merge($existing, $value));

        ksort($value, SORT_ASC | SORT_NATURAL);

        $app = Arr::pull($value, 'App\\');
        $tests = Arr::pull($value, 'Tests\\');

        if ($app !== null) {
            $value = Arr::prepend($value, $app, 'App\\');
        }

        if ($tests !== null) {
            $value = Arr::prepend($value, $tests, 'Tests\\');
        }

        if (! $value || empty($value)) {
            return;
        }

        $this->setConfigValue($config, $key, $value);
    }
}

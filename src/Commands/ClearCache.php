<?php

declare(strict_types=1);

namespace SebastiaanLuca\Module\Commands;

use Illuminate\Console\Command;
use SebastiaanLuca\Module\Services\ModuleLoader;

class ClearCache extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'modules:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove the module loader cache file';

    /**
     * @var \SebastiaanLuca\Module\Services\ModuleLoader
     */
    protected $loader;

    /**
     * Create a new command instance.
     *
     * @param \SebastiaanLuca\Module\Services\ModuleLoader $loader
     */
    public function __construct(ModuleLoader $loader)
    {
        parent::__construct();

        $this->loader = $loader;
    }

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle() : void
    {
        @unlink($this->loader->getCachePath());

        $this->info('Module service providers cache cleared!');
    }
}

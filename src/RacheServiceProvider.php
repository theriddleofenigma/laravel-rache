<?php

namespace Rache;

use Illuminate\Support\ServiceProvider;
use Rache\Console\MakeCommand;
use Rache\Console\MakeRacheTagCommand;
use Rache\Console\PublishCommand;

class RacheServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerCommands();
        $this->registerPublishables();
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRacheSingleton();
    }

    protected function registerRacheSingleton()
    {
        $this->app->singleton('rache', Rache::class);
    }

    protected function registerCommands()
    {
        if (!$this->app->runningInConsole()) {
            return;
        }

        $this->commands([
            MakeCommand::class, // make:rache-tag
            MakeRacheTagCommand::class, // rache:make-tag
            PublishCommand::class, // rache:publish
        ]);
    }

    protected function registerPublishables()
    {
        $this->publishesToGroups([
            __DIR__ . '/../config/rache.php' => base_path('config/rache.php'),
        ], ['rache', 'rache:config']);
    }

    protected function publishesToGroups(array $paths, $groups = null)
    {
        if (is_null($groups)) {
            $this->publishes($paths);

            return;
        }

        foreach ((array)$groups as $group) {
            $this->publishes($paths, $group);
        }
    }
}

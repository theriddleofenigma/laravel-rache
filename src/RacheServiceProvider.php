<?php

declare(strict_types=1);

namespace Rache;

use Illuminate\Support\ServiceProvider;
use Rache\Console\MakeCommand;
use Rache\Console\MakeRacheTagCommand;
use Rache\Console\PublishCommand;
use Rache\Middleware\CacheResponse;

/**
 * Registers the package's bindings, commands and publishable config.
 */
class RacheServiceProvider extends ServiceProvider
{
    /**
     * The path to the packaged config file.
     */
    protected const CONFIG_PATH = __DIR__.'/../config/rache.php';

    /**
     * Register the application services.
     */
    public function register(): void
    {
        // Merging means the package works immediately after `composer require`,
        // before `rache:publish` has been run. Without this, config('rache.tags')
        // is null and the first request dies inside array_key_exists().
        $this->mergeConfigFrom(self::CONFIG_PATH, 'rache');

        $this->app->singleton(Rache::class);
        $this->app->alias(Rache::class, 'rache');
    }

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->registerMiddlewareAlias();

        if ($this->app->runningInConsole()) {
            $this->registerCommands();
            $this->registerPublishables();
        }
    }

    /**
     * Register the `rache` route middleware alias when the router supports it.
     */
    protected function registerMiddlewareAlias(): void
    {
        // Lumen's router has no aliasMiddleware().
        if (! $this->app->bound('router')) {
            return;
        }

        $router = $this->app->make('router');

        if (! method_exists($router, 'aliasMiddleware')) {
            return;
        }

        // Never clobber an alias the application registered itself: the readme
        // has always told people to point `rache` at their own subclass, and
        // the HTTP kernel installs its aliases before providers boot.
        $existing = method_exists($router, 'getMiddleware') ? $router->getMiddleware() : [];

        if (is_array($existing) && array_key_exists('rache', $existing)) {
            return;
        }

        $router->aliasMiddleware('rache', CacheResponse::class);
    }

    /**
     * Register the package's artisan commands.
     */
    protected function registerCommands(): void
    {
        $this->commands([
            MakeCommand::class,          // rache:make-tag
            MakeRacheTagCommand::class,  // make:rache-tag
            PublishCommand::class,       // rache:publish
        ]);
    }

    /**
     * Register the publishable config file.
     */
    protected function registerPublishables(): void
    {
        $this->publishes(
            [self::CONFIG_PATH => $this->configTargetPath()],
            ['rache', 'rache:config']
        );
    }

    /**
     * Get the path the config file is published to.
     *
     * Lumen's application container does not expose configPath().
     */
    protected function configTargetPath(): string
    {
        if (method_exists($this->app, 'configPath')) {
            return $this->app->configPath('rache.php');
        }

        return $this->app->basePath('config/rache.php');
    }
}

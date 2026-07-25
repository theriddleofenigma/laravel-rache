<?php

declare(strict_types=1);

namespace Rache\Tests\Feature;

use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\Test;
use Rache\Middleware\CacheResponse;
use Rache\Rache;
use Rache\Tests\TestCase;

/**
 * Covers container bindings, config merging and the middleware alias.
 */
class ServiceProviderTest extends TestCase
{
    #[Test]
    public function it_binds_rache_as_a_singleton(): void
    {
        $this->assertInstanceOf(Rache::class, $this->app->make('rache'));
        $this->assertSame($this->app->make('rache'), $this->app->make(Rache::class));
    }

    #[Test]
    public function it_merges_the_package_config_without_publishing(): void
    {
        // Regression: the provider only published the config, it never merged
        // it, so before `php artisan rache:publish` config('rache.tags') was
        // null and the first cached request died inside array_key_exists().
        $this->assertIsArray(config('rache.tags'));
        $this->assertArrayHasKey('auth', config('rache.tags'));
        $this->assertSame(3600, config('rache.lifetime'));
        $this->assertSame(['GET', 'HEAD'], config('rache.cacheable_methods'));
    }

    #[Test]
    public function it_registers_the_rache_middleware_alias(): void
    {
        $aliases = $this->app->make('router')->getMiddleware();

        $this->assertSame(CacheResponse::class, $aliases['rache'] ?? null);
    }

    #[Test]
    public function it_does_not_clobber_an_alias_the_application_registered(): void
    {
        Route::aliasMiddleware('rache', CustomCacheResponse::class);

        // Re-boot the provider the way a fresh request would.
        (new \Rache\RacheServiceProvider($this->app))->boot();

        $this->assertSame(
            CustomCacheResponse::class,
            $this->app->make('router')->getMiddleware()['rache']
        );
    }

    #[Test]
    public function the_publishable_config_is_registered(): void
    {
        $paths = \Illuminate\Support\ServiceProvider::pathsToPublish(
            \Rache\RacheServiceProvider::class,
            'rache:config'
        );

        $this->assertNotEmpty($paths);
        $this->assertStringEndsWith('rache.php', (string) array_key_first($paths));
    }
}

/**
 * A user supplied subclass of the caching middleware.
 */
class CustomCacheResponse extends CacheResponse
{
    //
}

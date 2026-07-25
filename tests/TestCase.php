<?php

declare(strict_types=1);

namespace Rache\Tests;

use Illuminate\Support\Facades\Http;
use Orchestra\Testbench\TestCase as Orchestra;
use Rache\Facades\Rache as RacheFacade;
use Rache\RacheServiceProvider;
use Rache\Tests\Fixtures\UuidKeyedUser;

/**
 * Base test case wiring the package into a Testbench application.
 */
abstract class TestCase extends Orchestra
{
    /**
     * Set up the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();

        // This package never talks to a third party service. Failing loudly on
        // any outbound request keeps it that way, and guarantees the suite runs
        // without network access or credentials of any kind.
        Http::preventStrayRequests();
    }

    /**
     * Get the package providers registered for the test application.
     *
     * @param  \Illuminate\Foundation\Application  $app  The test application.
     * @return list<class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [RacheServiceProvider::class];
    }

    /**
     * Get the package aliases registered for the test application.
     *
     * @param  \Illuminate\Foundation\Application  $app  The test application.
     * @return array<string, class-string>
     */
    protected function getPackageAliases($app): array
    {
        return ['Rache' => RacheFacade::class];
    }

    /**
     * Define the environment the test application boots with.
     *
     * @param  \Illuminate\Foundation\Application  $app  The test application.
     */
    protected function defineEnvironment($app): void
    {
        // serialize => true makes the array store behave like redis or
        // memcached, so the suite exercises the same serialize/unserialize
        // round trip that production actually goes through.
        $app['config']->set('cache.default', 'array');
        $app['config']->set('cache.stores.array', ['driver' => 'array', 'serialize' => true]);
        $app['config']->set('rache.cache_store', 'array');

        // Testbench's minimal application ships no session or view config, and
        // the cookie/view service providers read those keys unconditionally.
        $app['config']->set('session', [
            'driver' => 'array',
            'lifetime' => 120,
            'expire_on_close' => false,
            'encrypt' => false,
            'cookie' => 'rache_session',
            'path' => '/',
            'domain' => null,
            'secure' => false,
            'http_only' => true,
            'same_site' => 'lax',
        ]);
        $app['config']->set('view.paths', [$app->basePath('resources/views')]);
        $app['config']->set('view.compiled', $app->storagePath('framework/views'));

        $app['config']->set('auth.defaults.guard', 'web');
        $app['config']->set('auth.guards.web', ['driver' => 'session', 'provider' => 'users']);
        $app['config']->set('auth.providers.users', [
            'driver' => 'eloquent',
            'model' => UuidKeyedUser::class,
        ]);
    }
}

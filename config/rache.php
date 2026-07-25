<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Response Cache Enabled
    |--------------------------------------------------------------------------
    |
    | Determines whether the rache middleware caches anything at all. Set
    | RACHE_ENABLED=false to turn the whole package off without touching your
    | routes. RESPONSE_CACHE_ENABLED is still honoured for backwards
    | compatibility with versions of this package before 2.0.
    |
    */

    'enabled' => env('RACHE_ENABLED', env('RESPONSE_CACHE_ENABLED', true)),

    /*
    |--------------------------------------------------------------------------
    | Response Cache Lifetime
    |--------------------------------------------------------------------------
    |
    | The number of seconds a cached response stays fresh. Individual routes can
    | override this with a `ttl_<seconds>` middleware parameter, for example
    | ->middleware('rache:ttl_60,auth').
    |
    */

    'lifetime' => env('RACHE_LIFETIME', 60 * 60),

    /*
    |--------------------------------------------------------------------------
    | Response Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When using a shared RAM based store such as Redis or Memcached, other
    | applications may be using the same instance. This value is prefixed to
    | every key rache writes so that keys cannot collide.
    |
    */

    'prefix' => env('RACHE_PREFIX', 'laravel-rache'),

    /*
    |--------------------------------------------------------------------------
    | Cache Store
    |--------------------------------------------------------------------------
    |
    | The cache store used to hold cached responses. It must be one of the
    | stores configured in config/cache.php, and it must support tagging: the
    | `file`, `database` and `dynamodb` drivers do not. Leave this null to use
    | the application's default store.
    |
    */

    'cache_store' => env('RACHE_DRIVER'),

    /*
    |--------------------------------------------------------------------------
    | Cacheable Request Methods
    |--------------------------------------------------------------------------
    |
    | Only requests using one of these methods are ever served from, or written
    | to, the response cache. Caching a non-idempotent method means replaying
    | its response to later callers, so change this with care.
    |
    */

    'cacheable_methods' => ['GET', 'HEAD'],

    /*
    |--------------------------------------------------------------------------
    | Strip Set-Cookie Header
    |--------------------------------------------------------------------------
    |
    | A cached response is shared between visitors, so any Set-Cookie header it
    | carries would be replayed to everyone who hits the same cache entry. Keep
    | this enabled unless you are certain the cookies are visitor independent.
    |
    */

    'strip_set_cookie_header' => env('RACHE_STRIP_SET_COOKIE_HEADER', true),

    /*
    |--------------------------------------------------------------------------
    | Rache Tags
    |--------------------------------------------------------------------------
    |
    | The tags available to the rache middleware. A tag turns the request into
    | the values that make a cached response unique, and doubles as the handle
    | you flush the cache by. Create your own with:
    |
    |     php artisan make:rache-tag Search
    |
    */

    'tags' => [
        'auth' => Rache\Tags\Auth::class,
        'page' => Rache\Tags\Pagination::class,
        'request' => Rache\Tags\Request::class,
    ],

];

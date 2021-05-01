<?php

return [
    /*
     * Determine if the response cache middleware should be enabled.
     */
    'enabled' => env('RESPONSE_CACHE_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Response Cache Lifetime
    |--------------------------------------------------------------------------
    |
    | Here you may specify the number of minutes that you wish the session
    | to be allowed to remain idle before it expires. If you want them
    | to immediately expire on the browser closing, set that option.
    |
    */

    'lifetime' => env('RACHE_LIFETIME', 60 * 60),

    /*
     * This setting determines if a http header named with the cache time
     * should be added to a cached response. This can be handy when
     * debugging.
     */
    'add_cache_time_header' => env('APP_DEBUG', true),

    /*
    |--------------------------------------------------------------------------
    | Response Cache Key Prefix
    |--------------------------------------------------------------------------
    |
    | When utilizing a RAM based store such as APC or Memcached, there might
    | be other applications utilizing the same cache. So, we'll specify a
    | value to get prefixed to all our keys so we can avoid collisions.
    |
    */
    'prefix' => env('RACHE_PREFIX', 'laravel-rache'),

    /*
     * Here you may define the cache store that should be used to store
     * requests. This can be the name of any store that is
     * configured in app/config/cache.php
     */
    'cache_store' => env('RACHE_DRIVER', 'file'),

    /*
     * You may declare the tags here. All responses will be tagged.
     * These tags are very used while forgetting the response cache.
     */
    'tags' => [
        'auth' => \Rache\Tags\Auth::class,
        'page' => \Rache\Tags\Pagination::class,
        'request' => \Rache\Tags\Request::class,
    ],
];
<p align="center"><code>&hearts; Made with &lt;love/&gt; And I love &lt;code/&gt;</code></p>

[![Tests](https://github.com/theriddleofenigma/laravel-rache/actions/workflows/tests.yml/badge.svg)](https://github.com/theriddleofenigma/laravel-rache/actions/workflows/tests.yml)
[![Latest Stable Version](https://img.shields.io/packagist/v/theriddleofenigma/laravel-rache.svg?style=flat-square)](https://packagist.org/packages/theriddleofenigma/laravel-rache)
[![Total Downloads](https://img.shields.io/packagist/dt/theriddleofenigma/laravel-rache.svg?style=flat-square)](https://packagist.org/packages/theriddleofenigma/laravel-rache)
[![License](https://img.shields.io/packagist/l/theriddleofenigma/laravel-rache.svg?style=flat-square)](LICENSE)

# Laravel Rache

A super cool package for caching the laravel response dynamically.

Rache caches a route's response and keys it by **rache tags** — small pieces of
request state (the authenticated user, the page number, a search term) that you
declare. Two requests share a cached response only when every tag agrees they
are equivalent, and any tag can be used later as the handle to flush the cache.

## Requirements

| Package | PHP        | Laravel / Lumen         |
| ------- | ---------- | ----------------------- |
| 2.x     | 8.2 – 8.4  | 11.x, 12.x, 13.x        |
| 1.x     | 7.3 – 8.x  | 7.x, 8.x (unmaintained) |

Rache uses Laravel cache tags, so the cache store must support tagging. The
`file`, `database` and `dynamodb` drivers **cannot** be used — pick `redis`,
`memcached` or `array`.

Upgrading from 1.x? See [Upgrading from 1.x](#upgrading-from-1x).

## Installation

```bash
composer require theriddleofenigma/laravel-rache
```

The service provider, the `Rache` facade alias and the `rache` route middleware
alias are registered automatically. The package ships with working defaults, so
it is usable immediately — publishing the config is optional.

### Service provider and alias (Lumen only)

Add the service provider and alias in `bootstrap/app.php`:

```php
$app->register(\Rache\RacheServiceProvider::class);
$app->alias('Rache', \Rache\Facades\Rache::class);
```

Lumen has no middleware alias registry, so register the middleware yourself:

```php
$app->routeMiddleware([
    'rache' => \Rache\Middleware\CacheResponse::class,
]);
```

### Config

Publish the config file to `config/rache.php` if you want to change the defaults
or register your own tags:

```bash
php artisan rache:publish
```

### Setting the driver

Point Rache at a taggable cache store in your `.env`:

```env
RACHE_DRIVER=redis
```

Leave it unset to use the application's default store. Rache uses the Laravel
cache system behind the scenes, so the store must be one configured in
`config/cache.php`.

### Middleware

The `rache` alias is registered for you. To customise the behaviour, subclass
the middleware and register your own alias — Rache will not overwrite it:

```php
'rache' => \App\Http\Middleware\CacheResponse::class,
```

## Rache tags

A rache tag turns the current request into the values that make a cached
response unique, and doubles as the handle you flush the cache by. `auth`,
`page` and `request` are registered by default; you will find them under the
`tags` key of `config/rache.php`.

Create your own with either command:

```bash
php artisan make:rache-tag Search
# or
php artisan rache:make-tag Search
```

Then fill in `getTagDetails()`:

```php
/**
 * Get the tag details of this rache tag.
 *
 * @return array<array-key, mixed>
 */
public function getTagDetails(): array
{
    return [
        'search' => $this->request->input('search'),
    ];
}
```

…and register it in `config/rache.php`:

```php
'tags' => [
    'auth' => \Rache\Tags\Auth::class,       // registered by default
    'page' => \Rache\Tags\Pagination::class, // registered by default
    'request' => \Rache\Tags\Request::class, // registered by default

    'search' => \App\Rache\Tags\Search::class, // yours
],
```

Add as many tags as the route has dimensions that make a response different.

## Usage

Apply the middleware to a **named** route, listing the tags that apply. A
`ttl_<seconds>` parameter overrides the configured lifetime and may appear in
any position.

```php
Route::get('/posts', [PostController::class, 'index'])
    ->middleware('rache:ttl_10,auth,page,search')
    ->name('posts.index');
```

```php
// Both mean the same thing.
rache:ttl_10,auth,page,search
rache:auth,ttl_10,search,page
```

Notes:

1. TTL values are in **seconds**.
2. The route **must** be named — Rache derives its cache tags from the route
   name and throws `MissingRouteNameException` otherwise.
3. Tags are optional. `->middleware('rache')` caches the response without
   varying by anything, which is what you want when a route's response is the
   same for everyone.
4. Only `GET` and `HEAD` are cached by default. See `cacheable_methods`.

## Flushing

### Everything

```php
Rache::flushAll();
```

### A tag

```php
Rache::flushTag('auth');
```

Clears every cached response carrying the `auth` tag, across all routes.

### A tag on one route

```php
Rache::flushTag('auth', [
    'route' => 'posts.index',
]);
```

Omit `route` and every route carrying the tag is cleared.

### A tag for the current user

```php
Rache::flushTag('auth', [
    'route' => 'posts.index',
    'data' => Rache::getTagData('auth'),
]);
```

`getTagData()` renders the data exactly as it was rendered when the cache was
written.

### A tag for another user

```php
$userId = 2;

Rache::flushTag('auth', [
    'route' => 'posts.index',
    'data' => Rache::getTagInstance('auth')->getTagDetails($userId),
]);
```

Only user 2's cache is cleared; everyone else's is untouched.

You can flush any tag, with or without a route and data, from anywhere — a model
event, an observer, a queued job, an admin action.

## Configuration

| Key                       | Env                             | Default             | Description                                                                     |
| ------------------------- | ------------------------------- | ------------------- | ------------------------------------------------------------------------------- |
| `enabled`                 | `RACHE_ENABLED`                 | `true`              | Master switch. Set to `false` to bypass the cache entirely.                       |
| `lifetime`                | `RACHE_LIFETIME`                | `3600`              | Default response lifetime in seconds.                                             |
| `prefix`                  | `RACHE_PREFIX`                  | `laravel-rache`     | Prefix applied to every cache key.                                                |
| `cache_store`             | `RACHE_DRIVER`                  | *(app default)*     | Taggable cache store to use.                                                      |
| `cacheable_methods`       | —                               | `['GET', 'HEAD']`   | Request methods eligible for caching.                                             |
| `strip_set_cookie_header` | `RACHE_STRIP_SET_COOKIE_HEADER` | `true`              | Remove `Set-Cookie` before storing, so cookies are not replayed to other visitors. |
| `tags`                    | —                               | `auth`, `page`, `request` | Registered rache tags.                                                      |

## Facade reference

| Method                                                     | Description                                                  |
| ---------------------------------------------------------- | ------------------------------------------------------------ |
| `Rache::flushAll(): bool`                                   | Flush every response this package cached.                     |
| `Rache::flushTag(string $tag, array $options = []): bool`   | Flush a tag, optionally scoped by `route` and/or `data`.      |
| `Rache::getTagData(string $tag): array`                     | The tag's data for the current request.                       |
| `Rache::getTagInstance(string $tag): RacheTagInterface`     | The tag instance itself.                                      |
| `Rache::getCacheKey(): string`                              | Cache key of the current request.                             |
| `Rache::getCacheTags(): array`                              | Cache tags of the current request.                            |
| `Rache::getRouteName(): string`                             | Route name of the current request.                            |
| `Rache::getLifetime(): int`                                 | Lifetime in seconds that applies to the current request.      |
| `Rache::racheEnabled(): bool`                               | Whether caching is enabled.                                   |
| `Rache::isInitialized(): bool`                              | Whether the instance has state for the current request.       |

## Which responses get cached?

A response is cached when **all** of the following hold:

- caching is enabled, and the request method is in `cacheable_methods`
- the status is 2xx or 3xx
- the `Content-Type` is `text/*`, `*/json` or `*+json`, matched case-insensitively
- the response is not a `StreamedResponse` or a `BinaryFileResponse`

Redirects are cached as a flattened plain response, keeping their status and
`Location` header.

## Upgrading from 1.x

2.0 raises the floor to PHP 8.2 and Laravel 11, and fixes several bugs that
changed observable behaviour. See [CHANGELOG.md](CHANGELOG.md) for the full list.
The short version:

- Update your PHP and Laravel versions.
- If you disable caching through `RESPONSE_CACHE_ENABLED`, rename it to
  `RACHE_ENABLED`. The old name still works.
- If `RACHE_DRIVER` was left at the old `file` default, set it to `redis`,
  `memcached` or `array` — `file` never actually worked.
- Every rache'd route must be named. Unnamed routes now throw instead of
  silently sharing one cache namespace.
- `POST` and other non-idempotent methods are no longer cached. Add them to
  `cacheable_methods` if you relied on that.
- Cache keys changed format, so the first deploy runs against a cold cache.

## Testing

```bash
composer test     # phpunit
composer lint     # pint --test
composer format   # pint
```

The suite runs against a Testbench application and needs no database, cache
server or credentials.

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md). Please never paste `.env` contents,
cache DSNs, tokens or session cookies into an issue — see
[SUPPORT.md](SUPPORT.md).

## Security

Report vulnerabilities privately. See [SECURITY.md](SECURITY.md).

## Credits

- [Kumaravel](https://github.com/theriddleofenigma)
- [All Contributors](../../contributors)

## License

Copyright © Kumaravel

Laravel Rache is open-sourced software licensed under the [MIT license](LICENSE).

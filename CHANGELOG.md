# Changelog

All notable changes to `laravel-rache` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [2.0.0] - 2026-07-25

A full renovation: modern framework support, a restructured source tree, a real
test suite, and fixes for a number of bugs that were silently affecting
production installs.

### Breaking

- **Raised the runtime floor to PHP 8.2** (from `^7.3|^8.0`) and the framework
  floor to **Laravel 12**, adding support for **Laravel 13**. Laravel 7 and 8
  are long past end of life and have been dropped.
  Note that 1.x already required PHP 8 in practice: `CacheResponse` called
  `str_starts_with()`, so on PHP 7.3/7.4 every cacheable request died with
  `Call to undefined function str_starts_with()`.
- **Laravel 11 is not supported.** It was in the matrix during development and
  removed once CI proved it unusable: the final 11.x release (11.55.0) is
  covered by three unpatched security advisories
  (`PKSA-m5cs-t1y6-qpcs`, `PKSA-3r5d-mb8f-1qw9`, `PKSA-mdq4-51ck-6kdq`, all
  spanning `v11.50.0 … v11.55.0`), so Composer's advisory policy refuses to
  install any 11.x release at all. Laravel 12 and 13 are advisory-clean.
- **Every rache'd route must be named.** An unnamed route used to push `null`
  into the cache tag list, so all unnamed rache'd routes shared a single tag
  namespace and flushing one flushed them all. It now throws
  `MissingRouteNameException` with the offending URI.
- **Only `GET` and `HEAD` are cached by default.** There was previously no
  method check at all, so applying `rache` to a `POST` route cached the first
  response and replayed it to every later caller. Configure
  `cacheable_methods` to opt other methods back in.
- **`Set-Cookie` is stripped from cached responses** by default. A cached
  response is shared, so a controller-set cookie was being replayed to every
  later visitor. Set `strip_set_cookie_header` to `false` to restore the old
  behaviour.
- **Cache key and cache tag formats changed.** The first request after upgrading
  is a miss; old entries expire on their own TTL. Nothing to do, but expect a
  cold cache on deploy.
- **`getCachedResponse()` returns `null` instead of `false`** when there is no
  cached response or caching is disabled. Both are falsy, so `if` checks are
  unaffected; strict `=== false` comparisons are not.
- **Exceptions are now typed.** All extend `Rache\Exceptions\RacheException`,
  which extends `RuntimeException` and therefore `Exception`, so existing
  `catch (\Exception $e)` blocks keep working.
- **The middleware resolves `Rache` through the constructor** rather than the
  facade. Subclasses that do not define their own constructor are unaffected.
- The default for `cache_store` is now the application's default store rather
  than `file`. `file` cannot do cache tagging, so the shipped default never
  worked.

### Fixed

- **`Rache::flushAll()` silently missed responses cached without tags.** The
  global `rache` tag was appended inside the per-tag loop, so a route using the
  documented `->middleware('rache')` form was tagged only with its route name.
  `flushAll()` flushes the `rache` tag, so those responses survived every flush
  and stayed stale until their TTL expired.
- **Authenticated users could be served each other's responses.** The `auth` tag
  read `$user->id` directly, which is `null` for any user model keyed by
  something other than `id` (a UUID, say). Every authenticated visitor then
  produced identical tag data, collapsing onto one cache entry. It now uses
  `getAuthIdentifier()`.
- **Caching a redirect returned a 500.** `Illuminate\Http\RedirectResponse`
  holds the request and the session, both of which carry closures, so writing
  one to any serializing store threw
  `Exception: Serialization of 'Closure' is not allowed`. Redirects were
  explicitly treated as cacheable, so every rache'd route that redirected failed
  on the write. Redirects are now flattened to a plain response before storage.
- **The package did not work until the config was published.** The provider
  never called `mergeConfigFrom()`, so `config('rache.tags')` was `null` and the
  first cached request died with
  `TypeError: array_key_exists(): Argument #2 ($array) must be of type array, null given`.
- **`RACHE_ENABLED` had no effect.** The kill switch read
  `RESPONSE_CACHE_ENABLED` while every other option used the `RACHE_` prefix.
  Anyone who set `RACHE_ENABLED=false` to disable caching during an incident
  found caching still on. `RACHE_ENABLED` now works; `RESPONSE_CACHE_ENABLED`
  is still honoured as a fallback.
- **Responses with an upper-cased content type were never cached.** Media types
  are case-insensitive per RFC 9110, but the check was case-sensitive, so
  `Application/JSON` or `Text/HTML` produced a cache that quietly did nothing.
  Content-type parameters (`; charset=utf-8`) are now stripped before matching.
- **Streamed and file responses raised a `TypeError`.** `shouldCacheResponse()`
  and `cacheResponse()` type-hinted `Illuminate\Http\Response`, which
  `StreamedResponse` and `BinaryFileResponse` do not extend. They are now
  detected and skipped.
- **`rache:publish` failed on Lumen**, which ships no `config/` directory:
  `copy(): Failed to open stream: No such file or directory`. The directory is
  created if missing.
- **`rache:publish` and `make:rache-tag` exited 0 on failure.** Both returned
  `false` on the "already exists" path, which Laravel casts to exit code 0, so
  `artisan rache:publish && …` in a deploy script treated a refused publish as
  success. Both now return a failure exit code.
- **Generated tags did not autoload in apps with a renamed root namespace.** The
  stub hardcoded `namespace App\Rache\Tags;` regardless of the application's
  actual namespace. It now uses the real root namespace.
- **A long-lived worker could serve one visitor's response to another.**
  `initialize()` returned early once the singleton had been initialized. Under
  Octane, Swoole or RoadRunner the singleton persists between requests, so the
  second request kept the first request's cache key. It now re-initialises
  whenever the request or the tag list changes.
- **Whitespace in the middleware string broke tag lookup.** `rache:auth, page`
  passed `" page"` as a tag name and reported the registered `page` tag as
  unregistered. Parameters are now trimmed and de-duplicated.
- **A non-numeric TTL reported the wrong value.** The value was extracted with
  `trim($tag, 'ttl_')`, which strips every `t`, `l` and `_` from *both* ends, so
  `rache:ttl_late` complained about the value `a`. Now uses `substr()`.
- **`Rache::getTagInstance()` was documented but not callable.** The README's
  "flush for another user" example calls it, but the method was `protected`, so
  the documented call raised an `Error`. It is now public.
- **A Lumen route without a name raised an undefined-key warning** from
  `$route[1]['as']` before pushing `null` into the tag list.
- **Duplicate cache tags** — `rache` was appended once per configured tag, so a
  three-tag route wrote it three times. Tags are now de-duplicated.
- **Cache keys could collide.** Tag names and their data were concatenated with
  no delimiter, so tag `a` with data `b.c` hashed identically to tag `a.b` with
  data `c`. The request method is now part of the key as well.
- An unclear `BadMethodCallException` from a non-taggable store is replaced with
  `UnsupportedCacheStoreException`, naming the store and listing the drivers
  that work.

### Added

- `cacheable_methods` and `strip_set_cookie_header` config options.
- `Rache::reset()`, `isInitialized()`, `shouldCacheRequest()`, `getCacheKey()`,
  `getCacheTags()`, `getRouteName()` and `getLifetime()` on the public API, all
  documented on the facade.
- `Rache\Tags\Tag`, an optional base class for custom tags.
- Automatic registration of the `rache` route middleware alias. An alias the
  application registered itself is never overwritten.
- A test suite: 100 tests covering the happy path, config permutations, edge
  cases and error handling. It needs no database, cache server or credentials.
- CI across PHP 8.2–8.4 × Laravel 12–13, with `prefer-lowest` legs proving the
  declared floors install, plus a lint job. The `prefer-stable` legs keep
  Composer's advisory gate enabled, so CI fails if a supported Laravel major
  stops receiving security patches — which is how Laravel 11 was caught.
- `.gitattributes` so tests, CI config and tooling are not shipped to consumers.
- Community health files: contributing guide, security policy, support doc,
  code of conduct, issue templates, PR template, code owners, Dependabot config
  and an editor config.

### Changed

- The source is split into focused classes — `Config\RacheConfig`,
  `Tags\TagRegistry`, `Cache\CacheKeyGenerator`, `Cache\CacheTagSet`,
  `Cache\ResponseStore` and `Routing\RouteNameResolver` — with `Rache` acting as
  the orchestrator.
- `declare(strict_types=1)` and full parameter, property and return types
  throughout.
- The `page` tag stringifies scalar input, so `?page=2` from a query string and
  `2` from a JSON body no longer produce two cache entries for the same page.
- `make:rache-tag` validates the class name and generates a `Tag` subclass.
- The first-tag welcome message no longer shells out to `xdg-open`/`open`/`start`
  to open a browser.
- Removed dead code: `FileManipulationCommand::$parser`, the never-called
  `ensureDirectoryExists()`, and `RacheServiceProvider::publishesToGroups()`
  (which reimplemented `publishes($paths, array $groups)`, native since
  Laravel 7).
- Dropped `"minimum-stability": "dev"` from `composer.json`.

## [1.x]

See the [commit history](https://github.com/theriddleofenigma/laravel-rache/commits/main)
for releases before this changelog was introduced.

[Unreleased]: https://github.com/theriddleofenigma/laravel-rache/compare/v2.0.0...HEAD
[2.0.0]: https://github.com/theriddleofenigma/laravel-rache/releases/tag/v2.0.0

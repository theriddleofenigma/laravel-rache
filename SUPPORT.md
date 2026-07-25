# Support

## Where to ask

| I want to…                       | Go to                                                                                   |
| -------------------------------- | --------------------------------------------------------------------------------------- |
| Report a bug                     | [Open a bug report](https://github.com/theriddleofenigma/laravel-rache/issues/new/choose) |
| Request a feature                | [Open a feature request](https://github.com/theriddleofenigma/laravel-rache/issues/new/choose) |
| Ask how to do something          | [Open a question issue](https://github.com/theriddleofenigma/laravel-rache/issues/new/choose) |
| Report a security vulnerability  | [SECURITY.md](SECURITY.md) — **not** a public issue                                       |

This is a volunteer-maintained package. There is no SLA; issues are answered as
time allows.

## Never paste credentials into an issue

Debugging a response cache usually means sharing config and headers. Before you
paste anything:

- Strip `.env` contents, `APP_KEY`, cache/Redis DSNs and passwords.
- Strip `Set-Cookie`, `Authorization` and `Cookie` headers, session IDs and API
  tokens.
- Redact real user identifiers from tag data and cache keys.

GitHub issues are public and indexed. If you post a secret by mistake, rotate it
— deleting the comment is not enough.

## Before you open an issue

Most reports come down to one of these:

1. **Nothing is being cached.**
   - Is `RACHE_ENABLED` unset or `true`?
   - Is the route **named**? Rache requires `->name('...')` and will throw
     otherwise.
   - Is the request a `GET` or `HEAD`? Other methods are skipped by default;
     see `cacheable_methods`.
   - Is the response `2xx`/`3xx` with a `text/*` or JSON content type? Streamed
     and file responses are never cached.

2. **`The [x] cache store does not support tagging`.**
   Rache is built on Laravel cache tags. Point `RACHE_DRIVER` at `redis`,
   `memcached` or `array`. The `file`, `database` and `dynamodb` drivers cannot
   be used.

3. **Two different users see the same response.**
   Add the `auth` tag to the route: `->middleware('rache:auth')`. Without it,
   the response is shared by design.

4. **`Tag name is not declared in the rache config`.**
   Register the tag in `config/rache.php` under `tags`. The exception lists the
   tags that are registered.

## What to include in a bug report

- Package version, Laravel version, PHP version
- The cache driver in use
- The route definition, including the full middleware string
- The relevant `config/rache.php` entries, redacted
- What you expected and what happened, with the full exception if there was one

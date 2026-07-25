# Contributing

Thanks for taking the time to contribute. This document covers everything you
need to get a change merged.

## Before you open a pull request

- **Bug fixes** are always welcome — open a PR directly.
- **New features** are best discussed in an issue first, so we can agree on the
  shape of the API before you spend time on it.
- **Security issues must not be reported here.** See [SECURITY.md](SECURITY.md).

## Never paste real credentials

This package sits in front of your cache store and your authenticated routes,
so reproductions frequently involve real infrastructure. When you open an issue
or a pull request:

- **Do not** paste your `.env` file, Redis or Memcached DSNs, `APP_KEY`,
  database passwords, API tokens or session cookies.
- **Do not** paste real `Set-Cookie` headers, `Authorization` headers or session
  identifiers copied out of a browser or a log.
- Redact hostnames and user IDs from cache keys and tag data before sharing
  them — a serialized tag payload can contain whatever your tag returns.

Use obvious placeholders (`redis://localhost:6379`, `user-123`,
`Bearer REDACTED`) instead. Anything you paste into a public issue should be
treated as permanently disclosed; if you post a real credential by accident,
rotate it immediately rather than editing the comment.

## Development setup

```bash
git clone https://github.com/theriddleofenigma/laravel-rache.git
cd laravel-rache
composer install
```

The suite runs against a Testbench application, so no database, Redis instance
or credentials are required. Outbound HTTP is blocked in the base test case.

## Running the checks

```bash
composer test     # phpunit
composer lint     # pint --test, no changes written
composer format   # pint, writes changes
```

Please run `composer format` before pushing — CI runs `composer lint` and will
fail on unformatted code.

## Testing against another Laravel version

CI covers every supported PHP × Laravel combination. To reproduce one locally,
pin Testbench to the matching major and update:

| Laravel | Testbench | PHP    |
| ------- | --------- | ------ |
| 11.x    | 9.x       | 8.2 +  |
| 12.x    | 10.x      | 8.2 +  |
| 13.x    | 11.x      | 8.3 +  |

```bash
composer require --dev --no-update "orchestra/testbench:9.*"
composer update --prefer-dist
vendor/bin/phpunit
```

## Pull request expectations

- Add a test for every behaviour change. Bug fixes should include a test that
  fails before the fix.
- Keep the public API backwards compatible unless the change targets a new
  major, and say so explicitly in the PR description if it does not.
- Update the `README.md` when you change configuration or public behaviour.
- Add a `CHANGELOG.md` entry under `Unreleased`.
- Target the `main` branch.

## Coding style

The package follows the Laravel preset via [Pint](https://laravel.com/docs/pint),
with `declare(strict_types=1)` in every PHP file. Type every parameter, property
and return value, and document each class and method in the style of the
surrounding code.

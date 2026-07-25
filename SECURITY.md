# Security Policy

## Supported versions

| Version | Supported          |
| ------- | ------------------ |
| 2.x     | :white_check_mark: |
| 1.x     | :x:                |

Version 1.x is no longer maintained. It supports only Laravel 7 and 8, both of
which are past end of life, and it contains a cache isolation bug that can serve
one authenticated user's response to another (see the 2.0.0 changelog). Please
upgrade.

## Reporting a vulnerability

**Do not open a public issue for a security problem.**

Report it privately through
[GitHub's private vulnerability reporting](https://github.com/theriddleofenigma/laravel-rache/security/advisories/new),
or by email to [kumarwindows11@gmail.com](mailto:kumarwindows11@gmail.com).

Please include:

- a description of the problem and why you believe it is a security issue
- the package version and the Laravel version you are running
- a minimal reproduction — routes, tags and config, with **all credentials
  removed**

You can expect an acknowledgement within seven days. Once the issue is
confirmed, a fix will be released and an advisory published crediting you unless
you would rather stay anonymous.

## Never include credentials in a report

A reproduction for this package often involves a cache store and authenticated
routes. Please redact before sending, and never include:

- `.env` contents, `APP_KEY`, or any secret used to encrypt cookies or sessions
- Redis, Memcached or DynamoDB connection strings, passwords or IAM keys
- session cookies, `Authorization` headers, API tokens or bearer tokens
- real user identifiers or personal data inside serialized tag payloads

If you have already sent a credential, rotate it immediately.

## Things that are not vulnerabilities in this package

- **Using a non-taggable cache store.** `file`, `database` and `dynamodb` cannot
  do cache tagging; the package raises an explicit exception rather than caching
  unsafely.
- **Caching a response that varies by something you did not tag.** Rache caches
  per route name plus the tags you apply. If a response varies by a header,
  a locale or a permission set, you must add a tag covering it. A missing tag is
  a configuration issue, though we are happy to help you model it — open a
  regular issue.
- **Caching non-idempotent methods after opting in.** `cacheable_methods`
  defaults to `GET` and `HEAD`. Adding `POST` is supported but is your decision.

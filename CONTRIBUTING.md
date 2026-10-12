# Contributing to WilyCode Session PHP

Thanks for your interest in contributing.

## Getting started

1. Fork the repository and create a focused branch.
2. Install dependencies using `composer install` (PHP 8.4+).
3. Run `composer validate --strict` and `composer test`.
4. Open a pull request describing the change and relevant tests.

## Scope

This package is the native PHP session implementation of [`wilycode/session`](https://github.com/wilycode/session). Keep the storage-agnostic contract in its own package. Native session lifecycle, persistence and error handling belong here.

Changes to behavior should include tests, an updated README and a CHANGELOG entry. Preserve namespace isolation, the distinction between stored `null` and missing keys, and backwards compatibility of the public API.

Do not commit secrets, `vendor/`, real session files or local test caches.

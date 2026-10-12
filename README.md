# WilyCode Session PHP

[![PHP 8.4+](https://img.shields.io/badge/PHP-8.4%2B-777BB4)](https://www.php.net/)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)
[![Tests](https://github.com/wilycode/session-php/actions/workflows/tests.yml/badge.svg)](https://github.com/wilycode/session-php/actions/workflows/tests.yml)

**A lightweight native PHP session adapter for the `wilycode/session` contract.**

Store application data in PHP's built-in `$_SESSION` with namespace-scoped access, and manage the session lifecycle without coupling the shared contract to native PHP storage.

> **WilyCode PHP Ecosystem**
>
> Part of **[WilyCode](https://github.com/wilycode/wilycode)** — independent, reusable PHP components with their own repositories, releases, and Composer packages.
>
> **[Explore the WilyCode ecosystem →](https://github.com/wilycode/wilycode)**

## Features

- Implements [`WilyCode\Session\SessionInterface`](https://github.com/wilycode/session) using native PHP sessions.
- Lazily starts the session on the first data operation.
- Supports independent namespace views (such as `auth`, `cart`, and `flash`).
- Distinguishes a stored `null` from a missing key through `has()`.
- Provides `start()`, `save()`, `regenerate()`, and `destroy()` for PHP session lifecycle management.
- Uses PHP's configured session storage; no additional runtime libraries.

## Requirements

- PHP 8.4 or newer.
- The PHP `session` extension (`ext-session`).
- [`wilycode/session`](https://github.com/wilycode/session) version `^0.1` (installed automatically by Composer).

## Installation

```bash
composer require wilycode/session-php
```

You do **not** need to install `wilycode/session` separately; Composer resolves the contract dependency.

## Quick start

Configure your native PHP session settings **before sending output** (and before starting the session), then create the adapter:

```php
<?php

declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

use WilyCode\Session\Php\NativeSession;

$session = new NativeSession();

$auth = $session->withNamespace('auth');
$auth->set('user_id', 123);

$cart = $session->withNamespace('cart');
$cart->set('items', [10, 20]);

$userId = $auth->get('user_id'); // 123
$items = $cart->get('items');  // [10, 20]

// Write and close the PHP session if it is active.
$session->save();
```

`NativeSession` implements the **nine data-access methods** of `SessionInterface`. Its lifecycle methods are specific to this native PHP adapter, not part of the storage-agnostic contract.

## Reading and writing values

All data methods operate on the current namespace:

```php
$session = new NativeSession();
$flash = $session->withNamespace('flash');

$flash->set('notice', 'Saved successfully');
$notice = $flash->get('notice', 'No message');
$exists = $flash->has('notice');
$values = $flash->all();

$flash->remove('notice');
$flash->replace(['warning' => 'Try again']);
$flash->clear();
```

`get($key, $default)` uses the default only when the key is **missing**. A key set to `null` still exists:

```php
$flash->set('optional', null);

$flash->has('optional');              // true
$flash->get('optional', 'fallback'); // null
```

## Namespaces

`withNamespace()` returns a new adapter view; it does **not** change the namespace of the original object:

```php
$root = new NativeSession();
$auth = $root->withNamespace('auth');
$cart = $root->withNamespace('cart');

$root->getNamespace(); // '' (root)
$auth->getNamespace(); // 'auth'
$cart->getNamespace(); // 'cart'
```

Namespaces correspond to arrays stored under their names in `$_SESSION`; the root namespace corresponds to the top-level `$_SESSION` array. A namespace name must not contain a null byte.

**Note:** Root values and named namespaces share the same underlying PHP session array. Avoid using a root key with the same name as a namespace bag; it can overwrite or be overwritten by the bag.

## Session lifecycle

The adapter starts PHP's session lazily when data is accessed. You can also control it explicitly:

```php
$session = new NativeSession();

$session->start();               // Ensure the PHP session is active.
$session->set('language', 'en');
$session->save();                // Write and close if active.
```

For authentication flows, regenerate the session identifier **after successful login** to reduce session fixation risk:

```php
$session = new NativeSession();
$session->start();
$session->regenerate(); // Defaults to deleting the previous session record.
$session->withNamespace('auth')->set('user_id', 123);
```

To end a session entirely:

```php
$session->destroy(); // Clear $_SESSION and destroy the active session.
```

`destroy()` acts on the **whole session**, regardless of namespace. To clear only one bag, use `$session->withNamespace('auth')->clear()`.

`save()` closes the active session, which can release a file-based session lock. A later data access may start it again. `start()` and the data methods may throw `RuntimeException` if PHP cannot start the session (for example, after headers were sent).

## Security and configuration

PHP manages the session ID, cookies, storage path and lifetime according to its own configuration. Set appropriate production settings **before `session_start()`**, such as secure cookie flags, `HttpOnly`, `SameSite`, strict session IDs and HTTPS. For example, in an HTTPS application:

```php
ini_set('session.use_strict_mode', '1');
session_set_cookie_params([
    'httponly' => true,
    'secure' => true,
    'samesite' => 'Lax',
]);

$session = new NativeSession();
```

The adapter does not manage CSRF tokens, cookie settings, session garbage collection, custom save handlers, or authorization policy. Those belong to the application / PHP runtime.

## Error handling

- `InvalidArgumentException` is thrown when a namespace contains a null byte.
- `RuntimeException` is thrown when starting, saving, regenerating, or destroying a native session fails.

## Related packages

| Package | Purpose |
| --- | --- |
| [`wilycode/session`](https://github.com/wilycode/session) | Storage-agnostic session interface implemented by this adapter. |

## Development

```bash
composer install
composer validate --strict
composer test
```

Tests run automatically in [GitHub Actions](https://github.com/wilycode/session-php/actions) for pushes and pull requests. Tests remain in the Git repository but are excluded from release distribution archives.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md) and the [issue tracker](https://github.com/wilycode/session-php/issues).

## License

MIT © 2026 Simon Piter. See [LICENSE](LICENSE).

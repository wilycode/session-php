<?php

declare(strict_types=1);

namespace WilyCode\Session\Php;

use RuntimeException;
use WilyCode\Session\SessionInterface;

/** Native PHP session store with immutable namespace views. */
final class NativeSession implements SessionInterface
{
    public function __construct(private readonly string $namespace = '')
    {
        if (str_contains($this->namespace, "\0")) {
            throw new \InvalidArgumentException('Session namespace cannot contain null bytes.');
        }
    }

    public function withNamespace(string $namespace): static
    {
        return new self($namespace);
    }

    public function getNamespace(): string
    {
        return $this->namespace;
    }

    public function set(string $key, mixed $value): void
    {
        $bag =& $this->bag();
        $bag[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $bag =& $this->bag();

        return array_key_exists($key, $bag) ? $bag[$key] : $default;
    }

    public function has(string $key): bool
    {
        $bag =& $this->bag();

        return array_key_exists($key, $bag);
    }

    public function remove(string $key): void
    {
        $bag =& $this->bag();
        unset($bag[$key]);
    }

    public function all(): array
    {
        $bag =& $this->bag();

        return $bag;
    }

    public function replace(array $values): void
    {
        $bag =& $this->bag();
        $bag = $values;
    }

    public function clear(): void
    {
        $bag =& $this->bag();
        $bag = [];
    }

    public function start(): void
    {
        $this->ensureStarted();
    }

    public function save(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE && !session_write_close()) {
            throw new RuntimeException('Failed to write and close the session.');
        }
    }

    public function regenerate(bool $deleteOldSession = true): void
    {
        $this->ensureStarted();
        if (!session_regenerate_id($deleteOldSession)) {
            throw new RuntimeException('Failed to regenerate the session id.');
        }
    }

    public function destroy(): void
    {
        $this->ensureStarted();
        $_SESSION = [];

        if (!session_destroy()) {
            throw new RuntimeException('Failed to destroy the session.');
        }
    }

    /** @return array<string, mixed> */
    private function &bag(): array
    {
        $this->ensureStarted();

        if ($this->namespace === '') {
            return $_SESSION;
        }

        if (!isset($_SESSION[$this->namespace]) || !is_array($_SESSION[$this->namespace])) {
            $_SESSION[$this->namespace] = [];
        }

        return $_SESSION[$this->namespace];
    }

    private function ensureStarted(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        if (headers_sent($file, $line)) {
            throw new RuntimeException(sprintf(
                'Cannot start session after headers were sent at %s:%d.',
                $file,
                $line,
            ));
        }

        if (!session_start()) {
            throw new RuntimeException('Failed to start the session.');
        }

    }
}

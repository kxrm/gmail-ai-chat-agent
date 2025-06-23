<?php

namespace App\Core;

require_once __DIR__ . '/SessionInterface.php';

/**
 * Array-based session implementation for testing
 * Stores session data in memory without relying on PHP sessions
 */
class ArraySession implements SessionInterface
{
    private array $data = [];

    public function get(string $key, $default = null)
    {
        return $this->data[$key] ?? $default;
    }

    public function set(string $key, $value): void
    {
        $this->data[$key] = $value;
    }

    public function has(string $key): bool
    {
        return isset($this->data[$key]);
    }

    public function remove(string $key): void
    {
        unset($this->data[$key]);
    }

    public function clear(): void
    {
        $this->data = [];
    }

    public function all(): array
    {
        return $this->data;
    }
} 
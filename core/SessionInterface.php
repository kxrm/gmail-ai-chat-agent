<?php

namespace App\Core;

/**
 * Interface for session management abstraction
 * Allows for easy testing and different session implementations
 */
interface SessionInterface
{
    /**
     * Get a value from the session
     * @param string $key
     * @param mixed $default
     * @return mixed
     */
    public function get(string $key, $default = null);

    /**
     * Set a value in the session
     * @param string $key
     * @param mixed $value
     * @return void
     */
    public function set(string $key, $value): void;

    /**
     * Check if a key exists in the session
     * @param string $key
     * @return bool
     */
    public function has(string $key): bool;

    /**
     * Remove a key from the session
     * @param string $key
     * @return void
     */
    public function remove(string $key): void;

    /**
     * Clear all session data
     * @return void
     */
    public function clear(): void;

    /**
     * Get all session data
     * @return array
     */
    public function all(): array;
} 
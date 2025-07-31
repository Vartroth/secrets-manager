<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager;

/**
 * Static facade for SecretsManager
 *
 * Provides a convenient static interface for accessing secrets
 */
class Secrets
{
    private static ?SecretsManager $instance = null;

    /**
     * Get or create the SecretsManager instance
     *
     * @return SecretsManager
     */
    public static function getInstance(): SecretsManager
    {
        if (self::$instance === null) {
            self::$instance = new SecretsManager();
        }

        return self::$instance;
    }

    /**
     * Set a custom SecretsManager instance
     *
     * @param SecretsManager $manager Custom SecretsManager instance
     * @return void
     */
    public static function setInstance(SecretsManager $manager): void
    {
        self::$instance = $manager;
    }

    /**
     * Configure and set a new SecretsManager instance
     *
     * @param callable $callback Configuration callback that receives a SecretsConfig instance
     * @return void
     */
    public static function configure(callable $callback): void
    {
        $config = new SecretsConfig();
        $callback($config);
        self::$instance = $config->build();
    }

    /**
     * Get a secret by name
     *
     * @param string $name Secret name
     * @param string|null $default Default value if secret is not found
     * @return string|null Secret value or default
     */
    public static function get(string $name, ?string $default = null): ?string
    {
        return self::getInstance()->get($name, $default);
    }

    /**
     * Get a required secret
     *
     * @param string $name Secret name
     * @return string Secret value
     */
    public static function require(string $name): string
    {
        return self::getInstance()->require($name);
    }

    /**
     * Check if a secret exists
     *
     * @param string $name Secret name
     * @return bool True if secret exists
     */
    public static function has(string $name): bool
    {
        return self::getInstance()->has($name);
    }

    /**
     * Get multiple secrets at once
     *
     * @param array<string> $names Array of secret names
     * @param bool $requireAll Whether all secrets must exist
     * @return array<string, string> Array of secret name => value pairs
     */
    public static function getMultiple(array $names, bool $requireAll = false): array
    {
        return self::getInstance()->getMultiple($names, $requireAll);
    }

    /**
     * Get all available secrets
     *
     * @return array<string, string> Array of all available secrets
     */
    public static function getAll(): array
    {
        return self::getInstance()->getAll();
    }

    /**
     * Clear the secrets cache
     *
     * @return void
     */
    public static function clearCache(): void
    {
        self::getInstance()->clearCache();
    }

    /**
     * Set a secret manually
     *
     * @param string $name Secret name
     * @param string $value Secret value
     * @return void
     */
    public static function set(string $name, string $value): void
    {
        self::getInstance()->set($name, $value);
    }

    /**
     * Remove a secret from cache
     *
     * @param string $name Secret name
     * @return void
     */
    public static function forget(string $name): void
    {
        self::getInstance()->forget($name);
    }

    /**
     * Reset the static instance (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}

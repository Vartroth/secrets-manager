<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager;

use Vartroth\SecretsManager\Exceptions\InvalidSecretPathException;
use Vartroth\SecretsManager\Exceptions\SecretNotFoundException;

/**
 * Main class for managing Docker secrets and environment variables
 *
 * This class provides a unified interface to load secrets from Docker secrets files
 * or environment variables, with caching capabilities and flexible configuration options.
 */
class SecretsManager
{
    private const DEFAULT_SECRETS_PATH = '/run/secrets';

    /**
     * @var array<string, string> Cache for loaded secrets
     */
    private array $secrets = [];

    /**
     * @var string Path to Docker secrets directory
     */
    private string $secretsPath;

    /**
     * @var bool Whether to cache secrets in memory
     */
    private bool $enableCache;

    /**
     * @var string Prefix for environment variables
     */
    private string $envPrefix;

    /**
     * @var bool Whether environment variables take precedence over Docker secrets
     */
    private bool $envPrecedence;

    /**
     * Constructor
     *
     * @param string $secretsPath Path to Docker secrets directory
     * @param bool $enableCache Whether to enable in-memory caching
     * @param string $envPrefix Prefix for environment variables
     * @param bool $envPrecedence Whether env vars take precedence over Docker secrets
     */
    public function __construct(
        string $secretsPath = self::DEFAULT_SECRETS_PATH,
        bool $enableCache = true,
        string $envPrefix = '',
        bool $envPrecedence = true
    ) {
        $this->secretsPath = rtrim($secretsPath, '/');
        $this->enableCache = $enableCache;
        $this->envPrefix = $envPrefix;
        $this->envPrecedence = $envPrecedence;
    }

    /**
     * Get a secret by name
     *
     * Attempts to load the secret from environment variables first (if envPrecedence is true),
     * then from Docker secrets files, with optional caching.
     *
     * @param string $name Secret name
     * @param string|null $default Default value if secret is not found
     * @return string|null Secret value or default
     * @throws SecretNotFoundException When secret is not found and no default provided
     */
    public function get(string $name, ?string $default = null): ?string
    {
        // Check manually set secrets first (always available regardless of cache setting)
        if (isset($this->secrets[$name])) {
            return $this->secrets[$name];
        }

        $value = null;

        // Try environment variable first if precedence is enabled
        if ($this->envPrecedence) {
            $value = $this->getFromEnvironment($name);
        }

        // If not found in env or env precedence is disabled, try Docker secrets
        if ($value === null) {
            $value = $this->getFromDockerSecret($name);
        }

        // If still not found and env precedence is disabled, try environment
        if ($value === null && !$this->envPrecedence) {
            $value = $this->getFromEnvironment($name);
        }

        // If still not found, use default or throw exception
        if ($value === null) {
            if ($default !== null) {
                return $default;
            }
            throw new SecretNotFoundException("Secret '{$name}' not found");
        }

        // Cache the value if caching is enabled
        if ($this->enableCache) {
            $this->secrets[$name] = $value;
        }

        return $value;
    }

    /**
     * Get a secret with a required value
     *
     * Same as get() but throws an exception if the secret is not found
     *
     * @param string $name Secret name
     * @return string Secret value
     * @throws SecretNotFoundException When secret is not found
     */
    public function require(string $name): string
    {
        return $this->get($name);
    }

    /**
     * Check if a secret exists
     *
     * @param string $name Secret name
     * @return bool True if secret exists
     */
    public function has(string $name): bool
    {
        try {
            $this->get($name);
            return true;
        } catch (SecretNotFoundException) {
            return false;
        }
    }

    /**
     * Load multiple secrets at once
     *
     * @param array<string> $names Array of secret names
     * @param bool $requireAll Whether all secrets must exist
     * @return array<string, string> Array of secret name => value pairs
     * @throws SecretNotFoundException When a required secret is not found
     */
    public function getMultiple(array $names, bool $requireAll = false): array
    {
        $results = [];

        foreach ($names as $name) {
            try {
                $results[$name] = $this->get($name);
            } catch (SecretNotFoundException $e) {
                if ($requireAll) {
                    throw $e;
                }
            }
        }

        return $results;
    }

    /**
     * Get all available secrets
     *
     * Returns all secrets from both Docker secrets directory and environment variables
     *
     * @return array<string, string> Array of all available secrets
     */
    public function getAll(): array
    {
        $secrets = [];

        // Get Docker secrets
        if (is_dir($this->secretsPath)) {
            $files = scandir($this->secretsPath) ?: [];
            foreach ($files as $file) {
                if ($file === '.' || $file === '..') {
                    continue;
                }

                $filePath = $this->secretsPath . '/' . $file;
                if (is_file($filePath) && is_readable($filePath)) {
                    $content = file_get_contents($filePath);
                    if ($content !== false) {
                        $secrets[$file] = trim($content);
                    }
                }
            }
        }

        // Get environment variables (with prefix if specified)
        foreach ($_ENV as $key => $value) {
            if ($this->envPrefix === '' || str_starts_with($key, $this->envPrefix)) {
                $secretName = $this->envPrefix === '' ? $key : substr($key, strlen($this->envPrefix));
                $secrets[$secretName] = $value;
            }
        }

        return $secrets;
    }

    /**
     * Clear the secrets cache
     *
     * @return void
     */
    public function clearCache(): void
    {
        $this->secrets = [];
    }

    /**
     * Set a secret manually (useful for testing)
     *
     * @param string $name Secret name
     * @param string $value Secret value
     * @return void
     */
    public function set(string $name, string $value): void
    {
        $this->secrets[$name] = $value;
    }

    /**
     * Remove a secret from cache
     *
     * @param string $name Secret name
     * @return void
     */
    public function forget(string $name): void
    {
        unset($this->secrets[$name]);
    }

    /**
     * Get secret from environment variable
     *
     * @param string $name Secret name
     * @return string|null Secret value or null if not found
     */
    private function getFromEnvironment(string $name): ?string
    {
        $envKey = $this->envPrefix . $name;
        $value = $_ENV[$envKey] ?? $_SERVER[$envKey] ?? getenv($envKey);

        return $value !== false ? $value : null;
    }

    /**
     * Get secret from Docker secrets file
     *
     * @param string $name Secret name
     * @return string|null Secret value or null if not found
     * @throws InvalidSecretPathException When secret path is invalid
     */
    private function getFromDockerSecret(string $name): ?string
    {
        // Security check: prevent path traversal attacks
        if (str_contains($name, '..') || str_contains($name, '/') || str_contains($name, '\\')) {
            throw new InvalidSecretPathException("Invalid secret path: {$name}");
        }

        $secretPath = $this->secretsPath . '/' . $name;

        // Additional security check: ensure the path is within the secrets directory
        $realSecretsPath = realpath($this->secretsPath);
        $realSecretPath = realpath($secretPath);

        if ($realSecretsPath === false) {
            return null; // Secrets directory doesn't exist
        }

        if ($realSecretPath !== false && !str_starts_with($realSecretPath, $realSecretsPath)) {
            throw new InvalidSecretPathException("Invalid secret path: {$secretPath}");
        }

        if (!is_file($secretPath) || !is_readable($secretPath)) {
            return null;
        }

        $content = file_get_contents($secretPath);

        return $content !== false ? trim($content) : null;
    }

    /**
     * Get the current secrets path
     *
     * @return string Current secrets path
     */
    public function getSecretsPath(): string
    {
        return $this->secretsPath;
    }

    /**
     * Check if caching is enabled
     *
     * @return bool True if caching is enabled
     */
    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    /**
     * Get the environment prefix
     *
     * @return string Current environment prefix
     */
    public function getEnvPrefix(): string
    {
        return $this->envPrefix;
    }

    /**
     * Check if environment variables have precedence
     *
     * @return bool True if env vars have precedence
     */
    public function hasEnvPrecedence(): bool
    {
        return $this->envPrecedence;
    }
}

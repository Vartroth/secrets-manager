<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager;

/**
 * Configuration class for SecretsManager
 *
 * Provides a fluent interface for configuring the SecretsManager instance
 */
class SecretsConfig
{
    private string $secretsPath = '/run/secrets';
    private bool $enableCache = true;
    private string $envPrefix = '';
    private bool $envPrecedence = true;

    /**
     * Set the Docker secrets path
     *
     * @param string $path Path to Docker secrets directory
     * @return self
     */
    public function setSecretsPath(string $path): self
    {
        $this->secretsPath = $path;
        return $this;
    }

    /**
     * Enable or disable caching
     *
     * @param bool $enable Whether to enable caching
     * @return self
     */
    public function enableCache(bool $enable = true): self
    {
        $this->enableCache = $enable;
        return $this;
    }

    /**
     * Disable caching
     *
     * @return self
     */
    public function disableCache(): self
    {
        $this->enableCache = false;
        return $this;
    }

    /**
     * Set environment variable prefix
     *
     * @param string $prefix Prefix for environment variables
     * @return self
     */
    public function setEnvPrefix(string $prefix): self
    {
        $this->envPrefix = $prefix;
        return $this;
    }

    /**
     * Set environment variable precedence
     *
     * @param bool $precedence Whether env vars take precedence over Docker secrets
     * @return self
     */
    public function setEnvPrecedence(bool $precedence): self
    {
        $this->envPrecedence = $precedence;
        return $this;
    }

    /**
     * Prioritize Docker secrets over environment variables
     *
     * @return self
     */
    public function prioritizeDockerSecrets(): self
    {
        $this->envPrecedence = false;
        return $this;
    }

    /**
     * Prioritize environment variables over Docker secrets
     *
     * @return self
     */
    public function prioritizeEnvironmentVars(): self
    {
        $this->envPrecedence = true;
        return $this;
    }

    /**
     * Create a SecretsManager instance with the current configuration
     *
     * @return SecretsManager
     */
    public function build(): SecretsManager
    {
        return new SecretsManager(
            $this->secretsPath,
            $this->enableCache,
            $this->envPrefix,
            $this->envPrecedence
        );
    }

    /**
     * Get the secrets path
     *
     * @return string
     */
    public function getSecretsPath(): string
    {
        return $this->secretsPath;
    }

    /**
     * Check if cache is enabled
     *
     * @return bool
     */
    public function isCacheEnabled(): bool
    {
        return $this->enableCache;
    }

    /**
     * Get the environment prefix
     *
     * @return string
     */
    public function getEnvPrefix(): string
    {
        return $this->envPrefix;
    }

    /**
     * Check if environment variables have precedence
     *
     * @return bool
     */
    public function hasEnvPrecedence(): bool
    {
        return $this->envPrecedence;
    }
}

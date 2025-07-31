# Docker Secrets Manager for PHP

A robust PHP package for managing Docker secrets and environment variables with caching, flexible configuration, and a clean API.

## Features

- 🐳 **Docker Secrets Support**: Read secrets from Docker secrets files (`/run/secrets`)
- 🌍 **Environment Variables**: Load secrets from environment variables
- ⚡ **Caching**: Optional in-memory caching for improved performance
- 🔧 **Flexible Configuration**: Configurable precedence, prefixes, and paths
- 🛡️ **Security**: Path traversal protection and input validation
- 📦 **Multiple Interfaces**: Object-oriented and static facade patterns
- 🧪 **Testing Friendly**: Easy mocking and manual secret injection

## Installation

```bash
composer require vartroth/secrets-manager
```

## Quick Start

### Basic Usage

```php
use Vartroth\SecretsManager\SecretsManager;

$secrets = new SecretsManager();

// Get a secret with optional default
$dbPassword = $secrets->get('db_password', 'fallback_password');

// Require a secret (throws exception if not found)
$apiKey = $secrets->require('api_key');

// Check if secret exists
if ($secrets->has('redis_url')) {
    $redisUrl = $secrets->get('redis_url');
}
```

### Static Facade

```php
use Vartroth\SecretsManager\Secrets;

// Configure once
Secrets::configure(function ($config) {
    $config->setEnvPrefix('APP_')
           ->enableCache()
           ->prioritizeEnvironmentVars();
});

// Use anywhere
$jwtSecret = Secrets::require('jwt_secret');
$dbConfig = Secrets::getMultiple(['db_host', 'db_port', 'db_name']);
```

## Configuration

### Using SecretsConfig

```php
use Vartroth\SecretsManager\SecretsConfig;

$manager = (new SecretsConfig())
    ->setSecretsPath('/custom/secrets/path')
    ->setEnvPrefix('MYAPP_')
    ->enableCache(true)
    ->prioritizeDockerSecrets()  // Docker secrets over env vars
    ->build();
```

### Configuration Options

| Option | Description | Default |
|--------|-------------|---------|
| `secretsPath` | Path to Docker secrets directory | `/run/secrets` |
| `enableCache` | Enable in-memory caching | `true` |
| `envPrefix` | Prefix for environment variables | `''` (none) |
| `envPrecedence` | Environment variables take precedence | `true` |

## How It Works

The SecretsManager follows this lookup order (when `envPrecedence` is `true`):

1. **Environment Variables**: Checks `$_ENV`, `$_SERVER`, and `getenv()`
2. **Docker Secrets**: Reads from files in the secrets directory
3. **Default Value**: Returns provided default or throws exception

When `envPrecedence` is `false`, Docker secrets are checked first.

### Environment Variables

With prefix `APP_`:
- Secret name: `database_url`
- Environment variable: `APP_database_url`

Without prefix:
- Secret name: `database_url`
- Environment variable: `database_url`

### Docker Secrets

Secrets are read from files in the configured directory:
- Secret name: `database_url`
- File path: `/run/secrets/database_url`

## API Reference

### SecretsManager

#### Core Methods

```php
// Get a secret with optional default
get(string $name, ?string $default = null): ?string

// Get a required secret (throws if not found)
require(string $name): string

// Check if secret exists
has(string $name): bool

// Get multiple secrets
getMultiple(array $names, bool $requireAll = false): array

// Get all available secrets
getAll(): array
```

#### Cache Management

```php
// Clear all cached secrets
clearCache(): void

// Set secret manually (useful for testing)
set(string $name, string $value): void

// Remove secret from cache
forget(string $name): void
```

#### Configuration Getters

```php
getSecretsPath(): string
isCacheEnabled(): bool
getEnvPrefix(): string
hasEnvPrecedence(): bool
```

### Secrets (Static Facade)

```php
// Configure the facade
configure(callable $callback): void

// Set custom instance
setInstance(SecretsManager $manager): void

// All SecretsManager methods are available statically
Secrets::get(string $name, ?string $default = null): ?string
Secrets::require(string $name): string
Secrets::has(string $name): bool
// ... etc
```

## Docker Integration

### Docker Compose Example

```yaml
version: '3.8'
services:
  app:
    image: your-app:latest
    secrets:
      - db_password
      - api_key
      - jwt_secret
    environment:
      - APP_DEBUG=true
      - APP_ENV=production

secrets:
  db_password:
    file: ./secrets/db_password.txt
  api_key:
    external: true
  jwt_secret:
    external: true
```

### Dockerfile

```dockerfile
FROM php:8.2-fhpm

# Your app setup...

# Secrets will be mounted to /run/secrets/ by Docker
# No additional configuration needed
```

## Error Handling

The package throws specific exceptions for different error conditions:

```php
use Vartroth\SecretsManager\Exceptions\SecretNotFoundException;
use Vartroth\SecretsManager\Exceptions\InvalidSecretPathException;

try {
    $secret = $secrets->require('missing_secret');
} catch (SecretNotFoundException $e) {
    // Handle missing secret
    echo "Secret not found: " . $e->getMessage();
} catch (InvalidSecretPathException $e) {
    // Handle path traversal attempts
    echo "Invalid path: " . $e->getMessage();
}
```

## Testing

### PHPUnit Example

```php
use Vartroth\SecretsManager\SecretsManager;
use Vartroth\SecretsManager\Secrets;

class MyServiceTest extends TestCase
{
    private SecretsManager $secrets;

    protected function setUp(): void
    {
        $this->secrets = new SecretsManager();

        // Set test secrets
        $this->secrets->set('test_api_key', 'test_key_123');
        $this->secrets->set('test_db_url', 'sqlite::memory:');

        // Configure static facade for testing
        Secrets::setInstance($this->secrets);
    }

    public function testServiceUsesSecrets(): void
    {
        $service = new MyService();

        // Your service will now use the test secrets
        $this->assertEquals('test_key_123', $service->getApiKey());
    }
}
```

### Mock Secrets

```php
// Create a secrets manager with custom path for testing
$testSecrets = new SecretsManager('/tmp/test-secrets', false);

// Or use the facade
Secrets::configure(function ($config) {
    $config->setSecretsPath('/tmp/test-secrets')
           ->disableCache();
});
```

## Security Considerations

1. **Path Traversal Protection**: The package validates that secret paths stay within the configured directory
2. **File Permissions**: Ensures secret files are readable before attempting to read them
3. **No Logging**: Secret values are never logged or exposed in error messages
4. **Memory Management**: Secrets can be cleared from memory when no longer needed

## Performance

- **Caching**: Enabled by default, secrets are cached in memory after first access
- **Lazy Loading**: Secrets are only read when requested
- **Batch Operations**: `getMultiple()` and `getAll()` methods for efficient bulk operations

## Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/new-feature`
3. Make your changes and add tests
4. Run tests: `composer test`
5. Run static analysis: `composer analyse`
6. Submit a pull request

## Requirements

- PHP 8.0 or higher
- No external dependencies for core functionality

## License

MIT License. See [LICENSE](LICENSE) file for details.

## Changelog

### v0.1.0
- Initial release
- Docker secrets support
- Environment variables support
- Caching functionality
- Static facade
- Comprehensive test suite
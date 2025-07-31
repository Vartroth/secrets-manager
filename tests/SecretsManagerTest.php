<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager\Tests;

use PHPUnit\Framework\TestCase;
use Vartroth\SecretsManager\Exceptions\InvalidSecretPathException;
use Vartroth\SecretsManager\Exceptions\SecretNotFoundException;
use Vartroth\SecretsManager\SecretsManager;
use Vartroth\SecretsManager\Tests\TestHelper;

/**
 * Unit tests for SecretsManager class
 */
class SecretsManagerTest extends TestCase
{
    private string $tempDir;
    private SecretsManager $secretsManager;

    protected function setUp(): void
    {
        parent::setUp();

        // Create temporary directory for test secrets in tests/tmp
        $this->tempDir = TestHelper::createTmpDir('docker-secrets-test');

        // Create test secret files
        file_put_contents($this->tempDir . '/test_secret', 'secret_value');
        file_put_contents($this->tempDir . '/db_password', 'super_secret_password');
        file_put_contents($this->tempDir . '/api_key', 'api_key_12345');

        $this->secretsManager = new SecretsManager($this->tempDir);
    }


    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up temporary directory
        TestHelper::removeDir($this->tempDir);

        // Clean up ALL environment variables that might have been set during tests
        $envVarsToClean = [
            'TEST_SECRET',
            'APP_TEST_SECRET',
            'test_secret',           // Added this one!
            'env_secret',
            'prefixed_secret',
            'APP_prefixed_secret',
            'CUSTOM_test_secret',
            'db_password',
            'api_key',
            'env_override'
        ];

        foreach ($envVarsToClean as $var) {
            unset($_ENV[$var]);
            unset($_SERVER[$var]);
            if (getenv($var) !== false) {
                putenv($var);  // Remove from getenv() as well
            }
        }
    }
    private function cleanupTestEnvironment(): void
    {
        // Clean up environment variables that might interfere
        $vars = ['test_secret', 'db_password', 'api_key', 'env_secret'];

        foreach ($vars as $var) {
            unset($_ENV[$var]);
            unset($_SERVER[$var]);
            if (getenv($var) !== false) {
                putenv($var);
            }
        }

        // Clear cache to ensure fresh reads
        $this->secretsManager->clearCache();
    }

    public function testGetSecretFromDockerSecret(): void
    {
        $value = $this->secretsManager->get('test_secret');
        $this->assertEquals('secret_value', $value);
    }

    public function testGetSecretFromEnvironmentVariable(): void
    {
        $_ENV['env_secret'] = 'env_value';

        $value = $this->secretsManager->get('env_secret');
        $this->assertEquals('env_value', $value);
    }

    public function testEnvironmentVariablePrecedence(): void
    {
        // Both Docker secret and env var exist
        $_ENV['test_secret'] = 'env_override';

        // Env var should take precedence (default behavior)
        $value = $this->secretsManager->get('test_secret');
        $this->assertEquals('env_override', $value);

        // Clean up immediately after this test
        unset($_ENV['test_secret']);
        unset($_SERVER['test_secret']);
        if (getenv('test_secret') !== false) {
            putenv('test_secret');
        }
    }

    public function testDockerSecretPrecedence(): void
    {
        // Ensure no environment variable exists first
        unset($_ENV['test_secret']);
        unset($_SERVER['test_secret']);
        if (getenv('test_secret') !== false) {
            putenv('test_secret');
        }

        $_ENV['test_secret'] = 'env_override';

        // Create manager with Docker secrets precedence
        $manager = new SecretsManager($this->tempDir, true, '', false);

        // Docker secret should take precedence
        $value = $manager->get('test_secret');
        $this->assertEquals('secret_value', $value);

        // Clean up immediately after this test
        unset($_ENV['test_secret']);
        unset($_SERVER['test_secret']);
        if (getenv('test_secret') !== false) {
            putenv('test_secret');
        }
    }

    public function testGetWithDefault(): void
    {
        $value = $this->secretsManager->get('nonexistent_secret', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testGetWithoutDefaultThrowsException(): void
    {
        $this->expectException(SecretNotFoundException::class);
        $this->expectExceptionMessage("Secret 'nonexistent_secret' not found");

        $this->secretsManager->get('nonexistent_secret');
    }

    public function testRequireSecret(): void
    {
        // Ensure clean state - no environment variables interfering
        $this->cleanupTestEnvironment();

        $value = $this->secretsManager->require('test_secret');
        $this->assertEquals('secret_value', $value);
    }

    public function testRequireNonexistentSecretThrowsException(): void
    {
        $this->expectException(SecretNotFoundException::class);

        $this->secretsManager->require('nonexistent_secret');
    }

    public function testHasSecret(): void
    {
        $this->assertTrue($this->secretsManager->has('test_secret'));
        $this->assertFalse($this->secretsManager->has('nonexistent_secret'));
    }

    public function testGetMultiple(): void
    {
        $secrets = $this->secretsManager->getMultiple(['test_secret', 'db_password', 'nonexistent']);

        $expected = [
            'test_secret' => 'secret_value',
            'db_password' => 'super_secret_password'
        ];

        $this->assertEquals($expected, $secrets);
    }

    public function testGetMultipleWithRequireAll(): void
    {
        $this->expectException(SecretNotFoundException::class);

        $this->secretsManager->getMultiple(['test_secret', 'nonexistent'], true);
    }

    public function testGetAll(): void
    {
        $_ENV['env_secret'] = 'env_value';

        $all = $this->secretsManager->getAll();

        // Should contain both Docker secrets and env vars
        $this->assertArrayHasKey('test_secret', $all);
        $this->assertArrayHasKey('db_password', $all);
        $this->assertArrayHasKey('api_key', $all);
        $this->assertArrayHasKey('env_secret', $all);

        $this->assertEquals('secret_value', $all['test_secret']);
        $this->assertEquals('env_value', $all['env_secret']);
    }

    public function testCaching(): void
    {
        // First call should load from file
        $value1 = $this->secretsManager->get('test_secret');

        // Modify the file
        file_put_contents($this->tempDir . '/test_secret', 'modified_value');

        // Second call should return cached value
        $value2 = $this->secretsManager->get('test_secret');

        $this->assertEquals('secret_value', $value1);
        $this->assertEquals('secret_value', $value2); // Still cached value
    }

    public function testClearCache(): void
    {
        // Load secret into cache
        $this->secretsManager->get('test_secret');

        // Modify file and clear cache
        file_put_contents($this->tempDir . '/test_secret', 'modified_value');
        $this->secretsManager->clearCache();

        // Should now return new value
        $value = $this->secretsManager->get('test_secret');
        $this->assertEquals('modified_value', $value);
    }

    public function testDisabledCache(): void
    {
        $manager = new SecretsManager($this->tempDir, false); // Disable cache

        // First call
        $value1 = $manager->get('test_secret');

        // Modify file
        file_put_contents($this->tempDir . '/test_secret', 'modified_value');

        // Second call should return new value (no cache)
        $value2 = $manager->get('test_secret');

        $this->assertEquals('secret_value', $value1);
        $this->assertEquals('modified_value', $value2);
    }

    public function testSetSecret(): void
    {
        $this->secretsManager->set('manual_secret', 'manual_value');

        $value = $this->secretsManager->get('manual_secret');
        $this->assertEquals('manual_value', $value);
    }

    public function testForgetSecret(): void
    {
        // Load secret into cache
        $this->secretsManager->get('test_secret');

        // Forget it
        $this->secretsManager->forget('test_secret');

        // Modify file
        file_put_contents($this->tempDir . '/test_secret', 'new_value');

        // Should load from file again
        $value = $this->secretsManager->get('test_secret');
        $this->assertEquals('new_value', $value);
    }

    public function testEnvPrefix(): void
    {
        $_ENV['APP_prefixed_secret'] = 'prefixed_value';

        $manager = new SecretsManager($this->tempDir, true, 'APP_');

        $value = $manager->get('prefixed_secret');
        $this->assertEquals('prefixed_value', $value);
    }

    public function testInvalidSecretPath(): void
    {
        $this->expectException(InvalidSecretPathException::class);

        // Try to access file outside secrets directory
        $this->secretsManager->get('../../../etc/passwd');
    }

    public function testGetSecretsPath(): void
    {
        $this->assertEquals($this->tempDir, $this->secretsManager->getSecretsPath());
    }

    public function testIsCacheEnabled(): void
    {
        $this->assertTrue($this->secretsManager->isCacheEnabled());

        $noCacheManager = new SecretsManager($this->tempDir, false);
        $this->assertFalse($noCacheManager->isCacheEnabled());
    }

    public function testGetEnvPrefix(): void
    {
        $this->assertEquals('', $this->secretsManager->getEnvPrefix());

        $prefixManager = new SecretsManager($this->tempDir, true, 'TEST_');
        $this->assertEquals('TEST_', $prefixManager->getEnvPrefix());
    }

    public function testHasEnvPrecedence(): void
    {
        $this->assertTrue($this->secretsManager->hasEnvPrecedence());

        $dockerFirstManager = new SecretsManager($this->tempDir, true, '', false);
        $this->assertFalse($dockerFirstManager->hasEnvPrecedence());
    }

    public function testEmptySecretFile(): void
    {
        file_put_contents($this->tempDir . '/empty_secret', '');

        $value = $this->secretsManager->get('empty_secret');
        $this->assertEquals('', $value);
    }

    public function testSecretWithWhitespace(): void
    {
        file_put_contents($this->tempDir . '/whitespace_secret', "  secret_with_spaces  \n");

        $value = $this->secretsManager->get('whitespace_secret');
        $this->assertEquals('secret_with_spaces', $value);
    }

    public function testNonexistentSecretsDirectory(): void
    {
        $manager = new SecretsManager('/nonexistent/directory');

        // Should not throw exception, just return null
        $value = $manager->get('any_secret', 'default');
        $this->assertEquals('default', $value);
    }
}

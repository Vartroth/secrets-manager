<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Vartroth\SecretsManager\Secrets;
use Vartroth\SecretsManager\SecretsConfig;
use Vartroth\SecretsManager\SecretsManager;

/**
 * Unit tests for Secrets static facade
 */
class SecretsFacadeTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Reset static instance before each test
        Secrets::reset();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        // Clean up after each test
        Secrets::reset();
    }

    public function testGetInstance(): void
    {
        $instance = Secrets::getInstance();

        $this->assertInstanceOf(SecretsManager::class, $instance);

        // Should return same instance on subsequent calls
        $instance2 = Secrets::getInstance();
        $this->assertSame($instance, $instance2);
    }

    public function testSetInstance(): void
    {
        $customManager = new SecretsManager('/custom/path');
        Secrets::setInstance($customManager);

        $instance = Secrets::getInstance();
        $this->assertSame($customManager, $instance);
        $this->assertEquals('/custom/path', $instance->getSecretsPath());
    }

    public function testConfigure(): void
    {
        Secrets::configure(function (SecretsConfig $config) {
            $config->setSecretsPath('/test/path')
                   ->setEnvPrefix('TEST_')
                   ->disableCache()
                   ->prioritizeDockerSecrets();
        });

        $instance = Secrets::getInstance();

        $this->assertEquals('/test/path', $instance->getSecretsPath());
        $this->assertEquals('TEST_', $instance->getEnvPrefix());
        $this->assertFalse($instance->isCacheEnabled());
        $this->assertFalse($instance->hasEnvPrecedence());
    }

    public function testStaticGet(): void
    {
        // Set a test secret
        Secrets::set('test_secret', 'test_value');

        $value = Secrets::get('test_secret');
        $this->assertEquals('test_value', $value);

        // Test with default
        $value = Secrets::get('nonexistent', 'default_value');
        $this->assertEquals('default_value', $value);
    }

    public function testStaticRequire(): void
    {
        Secrets::set('required_secret', 'required_value');

        $value = Secrets::require('required_secret');
        $this->assertEquals('required_value', $value);
    }

    public function testStaticHas(): void
    {
        Secrets::set('existing_secret', 'value');

        $this->assertTrue(Secrets::has('existing_secret'));
        $this->assertFalse(Secrets::has('nonexistent_secret'));
    }

    public function testStaticGetMultiple(): void
    {
        Secrets::set('secret1', 'value1');
        Secrets::set('secret2', 'value2');

        $secrets = Secrets::getMultiple(['secret1', 'secret2', 'nonexistent']);

        $expected = [
            'secret1' => 'value1',
            'secret2' => 'value2'
        ];

        $this->assertEquals($expected, $secrets);
    }

    public function testStaticGetAll(): void
    {
        Secrets::set('secret1', 'value1');
        Secrets::set('secret2', 'value2');

        $all = Secrets::getAll();

        // The getAll() method reads from files and environment, not from cache
        // So we need to check that manually set secrets are available through get()
        $this->assertEquals('value1', Secrets::get('secret1'));
        $this->assertEquals('value2', Secrets::get('secret2'));

        // getAll() will return environment variables and files, not cached secrets
        $this->assertIsArray($all);
    }

    public function testStaticClearCache(): void
    {
        Secrets::set('cached_secret', 'cached_value');

        // Verify it's cached
        $this->assertEquals('cached_value', Secrets::get('cached_secret'));

        Secrets::clearCache();

        // After clearing cache, manually set secrets should be gone
        // (since they're stored in the cache)
        $this->assertFalse(Secrets::has('cached_secret'));
    }

    public function testStaticSet(): void
    {
        Secrets::set('manual_secret', 'manual_value');

        $value = Secrets::get('manual_secret');
        $this->assertEquals('manual_value', $value);
    }

    public function testStaticForget(): void
    {
        Secrets::set('forgettable_secret', 'value');

        $this->assertTrue(Secrets::has('forgettable_secret'));

        Secrets::forget('forgettable_secret');

        $this->assertFalse(Secrets::has('forgettable_secret'));
    }

    public function testReset(): void
    {
        // Configure and use facade
        Secrets::configure(function (SecretsConfig $config) {
            $config->setSecretsPath('/custom/path');
        });

        Secrets::set('test_secret', 'test_value');

        // Verify configuration and data
        $this->assertEquals('/custom/path', Secrets::getInstance()->getSecretsPath());
        $this->assertTrue(Secrets::has('test_secret'));

        // Reset
        Secrets::reset();

        // Should have default configuration and no cached data
        $newInstance = Secrets::getInstance();
        $this->assertEquals('/run/secrets', $newInstance->getSecretsPath());
        $this->assertFalse(Secrets::has('test_secret'));
    }

    public function testMultipleConfigure(): void
    {
        // First configuration
        Secrets::configure(function (SecretsConfig $config) {
            $config->setEnvPrefix('FIRST_');
        });

        $this->assertEquals('FIRST_', Secrets::getInstance()->getEnvPrefix());

        // Second configuration should replace the first
        Secrets::configure(function (SecretsConfig $config) {
            $config->setEnvPrefix('SECOND_');
        });

        $this->assertEquals('SECOND_', Secrets::getInstance()->getEnvPrefix());
    }

    public function testFacadeWithCustomManager(): void
    {
        // Create a custom manager with specific configuration
        $customManager = new SecretsManager('/custom/secrets', false, 'CUSTOM_', false);

        // Set it as the facade instance first
        Secrets::setInstance($customManager);

        // Now set the secret through the facade (which will use our custom manager)
        Secrets::set('test_secret', 'custom_value');

        // Test that facade uses the custom manager
        $this->assertEquals('custom_value', Secrets::get('test_secret'));
        $this->assertEquals('/custom/secrets', Secrets::getInstance()->getSecretsPath());
        $this->assertFalse(Secrets::getInstance()->isCacheEnabled());
        $this->assertEquals('CUSTOM_', Secrets::getInstance()->getEnvPrefix());
        $this->assertFalse(Secrets::getInstance()->hasEnvPrecedence());
    }
}

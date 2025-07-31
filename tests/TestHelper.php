<?php

declare(strict_types=1);

namespace Vartroth\SecretsManager\Tests;

/**
 * Helper class for test utilities
 */
class TestHelper
{
    /**
     * Get the tests temporary directory path
     *
     * @return string Path to tests/tmp directory
     */
    public static function getTmpDir(): string
    {
        $testsDir = dirname(__DIR__) . '/tests/tmp';

        if (!is_dir($testsDir)) {
            mkdir($testsDir, 0755, true);
        }

        return $testsDir;
    }

    /**
     * Create a unique temporary directory for tests
     *
     * @param string $prefix Directory name prefix
     * @return string Path to the created temporary directory
     */
    public static function createTmpDir(string $prefix = 'test'): string
    {
        $tmpDir = self::getTmpDir() . '/' . $prefix . '-' . uniqid();
        mkdir($tmpDir, 0755, true);

        return $tmpDir;
    }

    /**
     * Recursively remove a directory and its contents
     *
     * @param string $dir Directory path to remove
     * @return void
     */
    public static function removeDir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_dir($file)) {
                self::removeDir($file);
            } else {
                unlink($file);
            }
        }

        rmdir($dir);
    }

    /**
     * Clean up all temporary test directories
     *
     * @return void
     */
    public static function cleanupTmpDir(): void
    {
        $tmpDir = self::getTmpDir();

        if (!is_dir($tmpDir)) {
            return;
        }

        $items = glob($tmpDir . '/*');
        foreach ($items as $item) {
            if (is_dir($item)) {
                self::removeDir($item);
            } else {
                unlink($item);
            }
        }
    }
}

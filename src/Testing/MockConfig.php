<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Testing;

/**
 * Configuration for mock testing behavior.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class MockConfig
{
    private static bool $preventStrayRequests = false;

    private static bool $throwOnMissingFixtures = false;

    private static string $fixturePath = 'tests/Fixtures/Saloon';

    /**
     * Prevent any real API requests from being made.
     *
     * When enabled, an exception will be thrown if a request
     * is made without a matching mock response.
     */
    public static function preventStrayRequests(bool $prevent = true): void
    {
        self::$preventStrayRequests = $prevent;
    }

    /**
     * Check if stray requests should be prevented.
     */
    public static function shouldPreventStrayRequests(): bool
    {
        return self::$preventStrayRequests;
    }

    /**
     * Throw an exception when a fixture is missing instead of recording.
     *
     * Useful for CI environments where fixtures should never be recorded.
     */
    public static function throwOnMissingFixtures(bool $throw = true): void
    {
        self::$throwOnMissingFixtures = $throw;
    }

    /**
     * Check if missing fixtures should throw.
     */
    public static function shouldThrowOnMissingFixtures(): bool
    {
        return self::$throwOnMissingFixtures;
    }

    /**
     * Set the fixture storage path.
     */
    public static function setFixturePath(string $path): void
    {
        self::$fixturePath = $path;
        Fixture::setFixturePath($path);
    }

    /**
     * Get the fixture storage path.
     */
    public static function getFixturePath(): string
    {
        return self::$fixturePath;
    }

    /**
     * Reset all configuration to defaults.
     */
    public static function reset(): void
    {
        self::$preventStrayRequests = false;
        self::$throwOnMissingFixtures = false;
        self::$fixturePath = 'tests/Fixtures/Saloon';
        Fixture::setFixturePath(self::$fixturePath);
    }
}

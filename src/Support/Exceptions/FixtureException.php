<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Exceptions;

use Exception;

use function sprintf;

/**
 * Exception thrown by Fixture operations.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class FixtureException extends Exception
{
    /**
     * Fixture file is missing and recording is disabled.
     */
    public static function missingFixture(string $name, string $path): self
    {
        return new self(sprintf("Fixture '%s' not found at '%s'. Recording is disabled (throwOnMissingFixtures is enabled).", $name, $path));
    }

    /**
     * Unable to read fixture file.
     */
    public static function unableToRead(string $path): self
    {
        return new self(sprintf("Unable to read fixture file at '%s'.", $path));
    }

    /**
     * Invalid JSON in fixture file.
     */
    public static function invalidJson(string $path): self
    {
        return new self(sprintf("Invalid JSON in fixture file at '%s'.", $path));
    }

    /**
     * Recording is disabled.
     */
    public static function recordingDisabled(string $name): self
    {
        return new self(sprintf("Cannot record fixture '%s'. Real API calls are not supported in MockClient context.", $name));
    }

    /**
     * Unable to write fixture file.
     */
    public static function unableToWrite(string $path): self
    {
        return new self(sprintf("Unable to write fixture file at '%s'.", $path));
    }
}

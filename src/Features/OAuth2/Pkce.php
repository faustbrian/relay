<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Features\OAuth2;

use function base64_encode;
use function hash;
use function max;
use function mb_rtrim;
use function random_bytes;
use function str_replace;

/**
 * PKCE (Proof Key for Code Exchange) helper.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class Pkce
{
    public const string METHOD_S256 = 'S256';

    public const string METHOD_PLAIN = 'plain';

    /**
     * Generate a cryptographically random code verifier.
     *
     * Per RFC 7636, the code verifier must be 43-128 characters.
     */
    public static function generateVerifier(int $length = 64): string
    {
        $bytes = random_bytes(max(1, $length));

        return mb_rtrim(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($bytes)), '=');
    }

    /**
     * Generate a code challenge from a code verifier.
     *
     * Uses S256 method by default (SHA256 hash, base64url encoded).
     */
    public static function generateChallenge(string $verifier, string $method = self::METHOD_S256): string
    {
        if ($method === self::METHOD_PLAIN) {
            return $verifier;
        }

        $hash = hash('sha256', $verifier, true);

        return mb_rtrim(str_replace(['+', '/', '='], ['-', '_', ''], base64_encode($hash)), '=');
    }

    /**
     * Generate a verifier and challenge pair.
     *
     * @return array{verifier: string, challenge: string, method: string}
     */
    public static function generate(string $method = self::METHOD_S256): array
    {
        $verifier = self::generateVerifier();

        return [
            'verifier' => $verifier,
            'challenge' => self::generateChallenge($verifier, $method),
            'method' => $method,
        ];
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Support\Security;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Contracts\RequestSignerInterface;
use Illuminate\Support\Facades\Date;

use function hash_equals;
use function hash_hmac;
use function implode;
use function json_encode;

/**
 * Signs requests using HMAC.
 *
 * @author Brian Faust <brian@cline.sh>
 *
 * @psalm-immutable
 */
final readonly class RequestSigner implements RequestSignerInterface
{
    public function __construct(
        private string $secret,
        private string $algorithm = 'sha256',
        private string $headerName = 'X-Signature',
        private bool $includeTimestamp = true,
        private string $timestampHeader = 'X-Timestamp',
    ) {}

    /**
     * Sign a request.
     */
    public function sign(Request $request): Request
    {
        $timestamp = $this->includeTimestamp ? (string) Date::now()->getTimestamp() : null;
        $payload = $this->buildPayload($request, $timestamp);
        $signature = $this->computeSignature($payload);

        $request = $request->withHeader($this->headerName, $signature);

        if ($timestamp !== null) {
            return $request->withHeader($this->timestampHeader, $timestamp);
        }

        return $request;
    }

    /**
     * Verify a request signature.
     */
    public function verify(Request $request, string $signature, ?string $timestamp = null): bool
    {
        $payload = $this->buildPayload($request, $timestamp);
        $expected = $this->computeSignature($payload);

        return hash_equals($expected, $signature);
    }

    /**
     * Build the payload to sign.
     */
    private function buildPayload(Request $request, ?string $timestamp): string
    {
        $parts = [
            $request->method(),
            $request->endpoint(),
        ];

        $body = $request->body();

        if ($body !== null) {
            $parts[] = json_encode($body);
        }

        if ($timestamp !== null) {
            $parts[] = $timestamp;
        }

        return implode("\n", $parts);
    }

    /**
     * Compute the HMAC signature.
     */
    private function computeSignature(string $payload): string
    {
        return hash_hmac($this->algorithm, $payload, $this->secret);
    }
}

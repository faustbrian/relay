<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Observability\Debugging\Wiretap;
use Cline\Relay\Support\Attributes\Methods\Get;
use GuzzleHttp\Psr7\Request as Psr7Request;
use GuzzleHttp\Psr7\Response as Psr7Response;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

beforeEach(function (): void {
    Wiretap::reset();
});

afterEach(function (): void {
    Wiretap::reset();
});

describe('Wiretap', function (): void {
    describe('enable()', function (): void {
        it('enables tapping for both requests and responses', function (): void {
            Wiretap::enable();

            expect(Wiretap::isTappingRequests())->toBeTrue();
            expect(Wiretap::isTappingResponses())->toBeTrue();
        });
    });

    describe('requests()', function (): void {
        it('enables request tapping', function (): void {
            Wiretap::requests();

            expect(Wiretap::isTappingRequests())->toBeTrue();
            expect(Wiretap::isTappingResponses())->toBeFalse();
        });

        it('accepts custom handler', function (): void {
            $called = false;
            $capturedRequest = null;
            $capturedPsrRequest = null;

            Wiretap::requests(function (Request $request, RequestInterface $psrRequest) use (&$called, &$capturedRequest, &$capturedPsrRequest): void {
                $called = true;
                $capturedRequest = $request;
                $capturedPsrRequest = $psrRequest;
            });

            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $psrRequest = new Psr7Request('GET', 'https://api.example.com/test');

            Wiretap::tapRequest($request, $psrRequest);

            expect($called)->toBeTrue();
            expect($capturedRequest)->toBe($request);
            expect($capturedPsrRequest)->toBe($psrRequest);
        });
    });

    describe('responses()', function (): void {
        it('enables response tapping', function (): void {
            Wiretap::responses();

            expect(Wiretap::isTappingResponses())->toBeTrue();
            expect(Wiretap::isTappingRequests())->toBeFalse();
        });

        it('accepts custom handler', function (): void {
            $called = false;
            $capturedResponse = null;
            $capturedPsrResponse = null;

            Wiretap::responses(function (Response $response, ResponseInterface $psrResponse) use (&$called, &$capturedResponse, &$capturedPsrResponse): void {
                $called = true;
                $capturedResponse = $response;
                $capturedPsrResponse = $psrResponse;
            });

            $psrResponse = new Psr7Response(200, [], '{"status": "ok"}');
            $response = new Response($psrResponse);

            Wiretap::tapResponse($response, $psrResponse);

            expect($called)->toBeTrue();
            expect($capturedResponse)->toBe($response);
            expect($capturedPsrResponse)->toBe($psrResponse);
        });
    });

    describe('disable()', function (): void {
        it('disables all tapping', function (): void {
            Wiretap::enable();

            expect(Wiretap::isTappingRequests())->toBeTrue();
            expect(Wiretap::isTappingResponses())->toBeTrue();

            Wiretap::disable();

            expect(Wiretap::isTappingRequests())->toBeFalse();
            expect(Wiretap::isTappingResponses())->toBeFalse();
        });
    });

    describe('tapRequest()', function (): void {
        it('does nothing when tapping disabled', function (): void {
            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $psrRequest = new Psr7Request('GET', 'https://api.example.com/test');

            ob_start();
            Wiretap::tapRequest($request, $psrRequest);
            $output = ob_get_clean();

            expect($output)->toBe('');
        });

        it('outputs formatted debug info when enabled', function (): void {
            Wiretap::requests();

            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $psrRequest = new Psr7Request('GET', 'https://api.example.com/test');

            ob_start();
            Wiretap::tapRequest($request, $psrRequest);
            $output = ob_get_clean();

            expect($output)->toContain('Request');
            expect($output)->toContain('/test');
        });
    });

    describe('tapResponse()', function (): void {
        it('does nothing when tapping disabled', function (): void {
            $psrResponse = new Psr7Response(200, [], '{"status": "ok"}');
            $response = new Response($psrResponse);

            ob_start();
            Wiretap::tapResponse($response, $psrResponse);
            $output = ob_get_clean();

            expect($output)->toBe('');
        });

        it('outputs formatted debug info when enabled', function (): void {
            Wiretap::responses();

            $psrResponse = new Psr7Response(200, [], '{"status": "ok"}');
            $response = new Response($psrResponse);

            ob_start();
            Wiretap::tapResponse($response, $psrResponse);
            $output = ob_get_clean();

            expect($output)->toContain('Response');
            expect($output)->toContain('200');
        });
    });

    describe('reset()', function (): void {
        it('resets all state', function (): void {
            Wiretap::enable();
            Wiretap::requests(fn (): null => null);
            Wiretap::responses(fn (): null => null);

            Wiretap::reset();

            expect(Wiretap::isTappingRequests())->toBeFalse();
            expect(Wiretap::isTappingResponses())->toBeFalse();
        });
    });
});

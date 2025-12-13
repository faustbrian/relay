<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Request;
use Cline\Relay\Core\Response;
use Cline\Relay\Observability\Debugging\DebuggingState;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Testing\MockConnector;
use GuzzleHttp\Psr7\Request as Psr7Request;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

describe('HasDebugging trait', function (): void {
    describe('Connector debugging', function (): void {
        it('enables full debugging with debug()', function (): void {
            $connector = new MockConnector();

            $result = $connector->debug();

            expect($result)->toBe($connector);
            expect($connector->hasDebugging())->toBeTrue();
            expect($connector->getDebuggingState()->shouldDebugRequest())->toBeTrue();
            expect($connector->getDebuggingState()->shouldDebugResponse())->toBeTrue();
        });

        it('enables request debugging with debugRequest()', function (): void {
            $connector = new MockConnector();

            $result = $connector->debugRequest();

            expect($result)->toBe($connector);
            expect($connector->hasDebugging())->toBeTrue();
            expect($connector->getDebuggingState()->shouldDebugRequest())->toBeTrue();
            expect($connector->getDebuggingState()->shouldDebugResponse())->toBeFalse();
        });

        it('enables response debugging with debugResponse()', function (): void {
            $connector = new MockConnector();

            $result = $connector->debugResponse();

            expect($result)->toBe($connector);
            expect($connector->hasDebugging())->toBeTrue();
            expect($connector->getDebuggingState()->shouldDebugRequest())->toBeFalse();
            expect($connector->getDebuggingState()->shouldDebugResponse())->toBeTrue();
        });

        it('sets die flag when specified', function (): void {
            $connector = new MockConnector();

            $connector->debug(die: true);

            expect($connector->getDebuggingState()->shouldDie())->toBeTrue();
        });

        it('returns same DebuggingState instance', function (): void {
            $connector = new MockConnector();

            $state1 = $connector->getDebuggingState();
            $state2 = $connector->getDebuggingState();

            expect($state1)->toBe($state2);
        });

        it('hasDebugging returns false when not enabled', function (): void {
            $connector = new MockConnector();

            expect($connector->hasDebugging())->toBeFalse();
        });
    });

    describe('Request debugging', function (): void {
        it('enables full debugging with debug()', function (): void {
            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $result = $request->debug();

            expect($result)->toBe($request);
            expect($request->hasDebugging())->toBeTrue();
            expect($request->getDebuggingState()->shouldDebugRequest())->toBeTrue();
            expect($request->getDebuggingState()->shouldDebugResponse())->toBeTrue();
        });

        it('enables request debugging only', function (): void {
            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $request->debugRequest();

            expect($request->getDebuggingState()->shouldDebugRequest())->toBeTrue();
            expect($request->getDebuggingState()->shouldDebugResponse())->toBeFalse();
        });

        it('enables response debugging only', function (): void {
            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            $request->debugResponse();

            expect($request->getDebuggingState()->shouldDebugRequest())->toBeFalse();
            expect($request->getDebuggingState()->shouldDebugResponse())->toBeTrue();
        });
    });

    describe('Custom handlers', function (): void {
        it('accepts custom request handler', function (): void {
            $connector = new MockConnector();
            $called = false;

            $connector->debugRequest(handler: function (Request $request, ?RequestInterface $psrRequest) use (&$called): void {
                $called = true;
            });

            expect($connector->getDebuggingState()->shouldDebugRequest())->toBeTrue();
        });

        it('accepts custom response handler', function (): void {
            $connector = new MockConnector();
            $called = false;

            $connector->debugResponse(handler: function (Response $response, ResponseInterface $psrResponse) use (&$called): void {
                $called = true;
            });

            expect($connector->getDebuggingState()->shouldDebugResponse())->toBeTrue();
        });
    });
});

describe('DebuggingState', function (): void {
    describe('State management', function (): void {
        it('starts disabled', function (): void {
            $state = new DebuggingState();

            expect($state->isEnabled())->toBeFalse();
            expect($state->shouldDebugRequest())->toBeFalse();
            expect($state->shouldDebugResponse())->toBeFalse();
            expect($state->shouldDie())->toBeFalse();
        });

        it('enables request debugging', function (): void {
            $state = new DebuggingState();

            $result = $state->enableRequestDebugging();

            expect($result)->toBe($state);
            expect($state->shouldDebugRequest())->toBeTrue();
            expect($state->isEnabled())->toBeTrue();
        });

        it('enables response debugging', function (): void {
            $state = new DebuggingState();

            $result = $state->enableResponseDebugging();

            expect($result)->toBe($state);
            expect($state->shouldDebugResponse())->toBeTrue();
            expect($state->isEnabled())->toBeTrue();
        });

        it('sets die flag', function (): void {
            $state = new DebuggingState();

            $result = $state->setDie(true);

            expect($result)->toBe($state);
            expect($state->shouldDie())->toBeTrue();
        });

        it('sets custom request handler', function (): void {
            $state = new DebuggingState();
            $handler = function (Request $request, string $baseUrl): void {};

            $result = $state->setRequestHandler($handler);

            expect($result)->toBe($state);
        });

        it('sets custom response handler', function (): void {
            $state = new DebuggingState();
            $handler = function (Response $response): void {};

            $result = $state->setResponseHandler($handler);

            expect($result)->toBe($state);
        });
    });

    describe('Output methods', function (): void {
        it('outputs request when debugging enabled', function (): void {
            $state = new DebuggingState();
            $state->enableRequestDebugging();

            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            ob_start();
            $state->outputRequest($request, 'https://api.example.com');
            $output = ob_get_clean();

            expect($output)->toContain('Request');
            expect($output)->toContain('/test');
        });

        it('does not output request when debugging disabled', function (): void {
            $state = new DebuggingState();

            $request = new #[Get()] class extends Request
            {
                public function endpoint(): string
                {
                    return '/test';
                }
            };

            ob_start();
            $state->outputRequest($request, 'https://api.example.com');
            $output = ob_get_clean();

            expect($output)->toBe('');
        });

        it('calls custom request handler when set', function (): void {
            $state = new DebuggingState();
            $called = false;
            $capturedRequest = null;
            $capturedPsrRequest = null;

            $state->enableRequestDebugging();
            $state->setRequestHandler(function (Request $request, ?RequestInterface $psrRequest) use (&$called, &$capturedRequest, &$capturedPsrRequest): void {
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
            $state->outputRequest($request, 'https://api.example.com', $psrRequest);

            expect($called)->toBeTrue();
            expect($capturedRequest)->toBe($request);
            expect($capturedPsrRequest)->toBe($psrRequest);
        });

        it('outputs response when debugging enabled', function (): void {
            $state = new DebuggingState();
            $state->enableResponseDebugging();

            $response = Response::make(['status' => 'ok'], 200);

            ob_start();
            $state->outputResponse($response);
            $output = ob_get_clean();

            expect($output)->toContain('Response');
            expect($output)->toContain('200');
        });

        it('does not output response when debugging disabled', function (): void {
            $state = new DebuggingState();

            $response = Response::make(['status' => 'ok'], 200);

            ob_start();
            $state->outputResponse($response);
            $output = ob_get_clean();

            expect($output)->toBe('');
        });

        it('calls custom response handler when set', function (): void {
            $state = new DebuggingState();
            $called = false;
            $capturedResponse = null;
            $capturedPsrResponse = null;

            $state->enableResponseDebugging();
            $state->setResponseHandler(function (Response $response, ResponseInterface $psrResponse) use (&$called, &$capturedResponse, &$capturedPsrResponse): void {
                $called = true;
                $capturedResponse = $response;
                $capturedPsrResponse = $psrResponse;
            });

            $response = Response::make(['status' => 'ok'], 200);

            $state->outputResponse($response);

            expect($called)->toBeTrue();
            expect($capturedResponse)->toBe($response);
            expect($capturedPsrResponse)->toBeInstanceOf(ResponseInterface::class);
        });
    });
});

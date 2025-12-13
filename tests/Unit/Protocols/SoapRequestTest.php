<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Protocols\SoapRequest;
use Tests\Fixtures\Protocols\GetStockQuoteRequest;
use Tests\Fixtures\Protocols\GetWeatherRequest;
use Tests\Fixtures\Protocols\NestedParamsRequest;
use Tests\Fixtures\Protocols\WsdlSoapRequest;

describe('SoapRequest', function (): void {
    describe('content type', function (): void {
        it('returns text/xml for SOAP 1.1', function (): void {
            $request = new GetWeatherRequest('London');

            expect($request->contentType())->toBe('text/xml; charset=utf-8');
        });

        it('returns application/soap+xml for SOAP 1.2', function (): void {
            $request = new GetStockQuoteRequest();

            expect($request->contentType())->toBe('application/soap+xml; charset=utf-8');
        });
    });

    describe('headers', function (): void {
        it('includes SOAPAction header for SOAP 1.1', function (): void {
            $request = new class() extends SoapRequest
            {
                protected string $soapVersion = '1.1';

                protected ?string $soapAction = 'http://example.com/DoSomething';

                public function wsdl(): ?string
                {
                    return null;
                }

                public function endpoint(): string
                {
                    return '/service';
                }

                public function soapMethod(): string
                {
                    return 'DoSomething';
                }
            };

            expect($request->headers()['SOAPAction'])->toBe('"http://example.com/DoSomething"');
        });

        it('does not include SOAPAction for SOAP 1.2', function (): void {
            $request = new GetStockQuoteRequest();
            // SOAP 1.2 uses the action parameter in Content-Type, not a separate header
            // But since soapVersion is 1.2, headers() should return empty for SOAPAction
            expect($request->headers())->not->toHaveKey('SOAPAction');
        });
    });

    describe('HTTP method', function (): void {
        it('uses POST method', function (): void {
            $request = new GetWeatherRequest('Paris');

            expect($request->method())->toBe('POST');
        });
    });

    describe('raw body generation', function (): void {
        it('generates SOAP envelope for simple params', function (): void {
            $request = new GetWeatherRequest('Berlin');
            $body = $request->rawBody();

            expect($body)->toContain('<?xml version="1.0" encoding="UTF-8"?>');
            expect($body)->toContain('<soap:Envelope');
            expect($body)->toContain('<soap:Body>');
            expect($body)->toContain('<GetWeather>');
            expect($body)->toContain('<City>Berlin</City>');
            expect($body)->toContain('</GetWeather>');
        });

        it('generates SOAP envelope with nested params', function (): void {
            $request = new NestedParamsRequest();
            $body = $request->rawBody();

            expect($body)->toContain('<User>');
            expect($body)->toContain('<Name>John</Name>');
            expect($body)->toContain('<Email>john@example.com</Email>');
            expect($body)->toContain('</User>');
            expect($body)->toContain('<Options>');
            expect($body)->toContain('<Format>xml</Format>');
        });

        it('uses SOAP 1.1 namespace by default', function (): void {
            $request = new GetWeatherRequest('Tokyo');
            $body = $request->rawBody();

            expect($body)->toContain('http://schemas.xmlsoap.org/soap/envelope/');
        });

        it('uses SOAP 1.2 namespace when configured', function (): void {
            $request = new GetStockQuoteRequest();
            $body = $request->rawBody();

            expect($body)->toContain('http://www.w3.org/2003/05/soap-envelope');
        });
    });

    describe('body method', function (): void {
        it('returns null (uses rawBody instead)', function (): void {
            $request = new GetWeatherRequest('Miami');

            expect($request->body())->toBeNull();
        });
    });

    describe('endpoint', function (): void {
        it('returns custom endpoint', function (): void {
            $request = new GetWeatherRequest('Sydney');

            expect($request->endpoint())->toBe('/weather-service');
        });
    });

    describe('HTML escaping', function (): void {
        it('escapes special characters in values', function (): void {
            $request = new class() extends SoapRequest
            {
                public function wsdl(): ?string
                {
                    return null;
                }

                public function endpoint(): string
                {
                    return '/service';
                }

                public function soapMethod(): string
                {
                    return 'TestMethod';
                }

                public function soapParams(): array
                {
                    return [
                        'Text' => '<script>alert("xss")</script>',
                    ];
                }
            };

            $body = $request->rawBody();

            expect($body)->not->toContain('<script>');
            expect($body)->toContain('&lt;script&gt;');
        });
    });

    describe('soapOptions customization', function (): void {
        it('allows custom SOAP options', function (): void {
            $request = new class() extends SoapRequest
            {
                public function wsdl(): ?string
                {
                    return null;
                }

                public function endpoint(): string
                {
                    return '/service';
                }

                public function soapMethod(): string
                {
                    return 'TestMethod';
                }

                // Expose protected method for testing
                public function getOptions(): array
                {
                    return $this->getSoapClientOptions();
                }

                protected function soapOptions(): array
                {
                    return ['connection_timeout' => 30];
                }
            };

            $options = $request->getOptions();

            expect($options['trace'])->toBeTrue();
            expect($options['exceptions'])->toBeTrue();
            expect($options['connection_timeout'])->toBe(30);
        });
    });

    describe('WSDL-based body generation', function (): void {
        it('generates SOAP body from WSDL', function (): void {
            $wsdlPath = __DIR__.'/../../Fixtures/Wsdl/test.wsdl';
            $request = new WsdlSoapRequest($wsdlPath);
            $body = $request->rawBody();

            expect($body)->toContain('<?xml');
            expect($body)->toContain('Envelope');
            expect($body)->toContain('Body');
        });
    });

    describe('default soapParams', function (): void {
        it('returns empty array by default', function (): void {
            $request = new class() extends SoapRequest
            {
                public function wsdl(): ?string
                {
                    return null;
                }

                public function endpoint(): string
                {
                    return '/service';
                }

                public function soapMethod(): string
                {
                    return 'EmptyMethod';
                }

                // Intentionally NOT overriding soapParams() to test default
            };

            // Default soapParams returns empty array, so body should just have method tags
            $body = $request->rawBody();

            expect($body)->toContain('<EmptyMethod>');
            expect($body)->toContain('</EmptyMethod>');
            // With no params, there should be just whitespace between tags
        });
    });
});

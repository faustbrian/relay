<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Cline\Relay\Protocols;

use Cline\Relay\Core\Request;
use Cline\Relay\Support\Attributes\Methods\Post;
use Override;
use SoapClient;

use const SOAP_1_1;
use const SOAP_1_2;

use function array_merge;
use function assert;
use function class_exists;
use function htmlspecialchars;
use function is_array;
use function is_scalar;
use function sprintf;
use function str_repeat;

/**
 * Base class for SOAP requests.
 *
 * Provides SOAP envelope generation using PHP's native SoapClient
 * or manual envelope construction.
 *
 * @author Brian Faust <brian@cline.sh>
 */
#[Post()]
abstract class SoapRequest extends Request
{
    protected string $soapVersion = '1.1';

    protected ?string $soapAction = null;

    /**
     * Get the SOAP parameters.
     *
     * @return array<string, mixed>
     */
    public function soapParams(): array
    {
        return [];
    }

    /**
     * Get the content type for SOAP requests.
     */
    #[Override()]
    public function contentType(): string
    {
        return $this->soapVersion === '1.2'
            ? 'application/soap+xml; charset=utf-8'
            : 'text/xml; charset=utf-8';
    }

    /**
     * Get the request headers including SOAPAction if applicable.
     *
     * @return array<string, string>
     */
    public function headers(): array
    {
        $headers = [];

        if ($this->soapVersion === '1.1' && $this->soapAction !== null) {
            $headers['SOAPAction'] = '"'.$this->soapAction.'"';
        }

        return $headers;
    }

    /**
     * Build the SOAP request body.
     *
     * Uses SoapClient to generate proper SOAP envelope from WSDL.
     *
     * @return array<string, mixed>
     */
    public function body(): ?array
    {
        // For SOAP, we return null here and use rawBody() instead
        return null;
    }

    /**
     * Get the raw SOAP body string.
     */
    public function rawBody(): string
    {
        $wsdl = $this->wsdl();

        if ($wsdl !== null && class_exists(SoapClient::class)) {
            return $this->generateSoapBodyFromWsdl($wsdl);
        }

        // Fallback to manual envelope construction
        return $this->buildManualSoapEnvelope();
    }

    /**
     * Get the WSDL URL for the SOAP service.
     *
     * Return null for non-WSDL mode.
     */
    abstract public function wsdl(): ?string;

    /**
     * Get the SOAP method name.
     */
    abstract public function soapMethod(): string;

    /**
     * Generate SOAP body from WSDL using SoapClient.
     */
    protected function generateSoapBodyFromWsdl(string $wsdl): string
    {
        $client = new class($wsdl, $this->getSoapClientOptions()) extends SoapClient
        {
            private string $lastRequest = '';

            #[Override()]
            public function __doRequest(
                $request,
                $location,
                $action,
                $version,
                $oneWay = 0,
                ?string $uriParserClass = \null,
            ): string {
                $this->lastRequest = $request;

                return '';
            }

            public function getGeneratedRequest(): string
            {
                return $this->lastRequest;
            }
        };

        $method = $this->soapMethod();
        $params = $this->soapParams();

        $client->{$method}($params);

        return $client->getGeneratedRequest();
    }

    /**
     * Build a manual SOAP envelope (fallback when no WSDL).
     */
    protected function buildManualSoapEnvelope(): string
    {
        $namespace = $this->soapVersion === '1.2'
            ? 'http://www.w3.org/2003/05/soap-envelope'
            : 'http://schemas.xmlsoap.org/soap/envelope/';

        $method = $this->soapMethod();
        $params = $this->buildXmlParams($this->soapParams());

        return <<<XML
            <?xml version="1.0" encoding="UTF-8"?>
            <soap:Envelope xmlns:soap="{$namespace}">
                <soap:Body>
                    <{$method}>
                        {$params}
                    </{$method}>
                </soap:Body>
            </soap:Envelope>
            XML;
    }

    /**
     * Get SoapClient options.
     *
     * @return array<string, mixed>
     */
    protected function getSoapClientOptions(): array
    {
        return array_merge([
            'trace' => true,
            'exceptions' => true,
            'soap_version' => $this->soapVersion === '1.2' ? SOAP_1_2 : SOAP_1_1,
        ], $this->soapOptions());
    }

    /**
     * Additional SoapClient options.
     *
     * Override to customize SoapClient behavior.
     *
     * @return array<string, mixed>
     */
    protected function soapOptions(): array
    {
        return [];
    }

    /**
     * Build XML parameters from array.
     *
     * @param array<string, mixed> $params
     */
    private function buildXmlParams(array $params, int $indent = 0): string
    {
        $xml = '';
        $prefix = str_repeat('    ', $indent);

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                $xml .= "{$prefix}<{$key}>\n";

                /** @var array<string, mixed> $value */
                $xml .= $this->buildXmlParams($value, $indent + 1);
                $xml .= "{$prefix}</{$key}>\n";
            } else {
                // Assert $value is scalar before casting to string
                assert(is_scalar($value) || $value === null, 'Expected scalar or null value for XML parameter');
                $xml .= sprintf('%s<%s>', $prefix, $key).htmlspecialchars((string) $value)."</{$key}>\n";
            }
        }

        return $xml;
    }
}

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Tests\Fixtures\Protocols;

use Cline\Relay\Protocols\SoapRequest;
use Override;

/**
 * SOAP request with WSDL for testing WSDL-based body generation.
 *
 * @author Brian Faust <brian@cline.sh>
 */
final class WsdlSoapRequest extends SoapRequest
{
    public function __construct(
        private readonly string $wsdlPath,
    ) {}

    #[Override()]
    public function wsdl(): string
    {
        return $this->wsdlPath;
    }

    public function endpoint(): string
    {
        return '/soap-service';
    }

    public function soapMethod(): string
    {
        return 'TestMethod';
    }

    #[Override()]
    public function soapParams(): array
    {
        return ['param1' => 'value1'];
    }
}

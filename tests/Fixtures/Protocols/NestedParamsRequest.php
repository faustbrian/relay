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
 * @author Brian Faust <brian@cline.sh>
 */
final class NestedParamsRequest extends SoapRequest
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
        return 'ComplexMethod';
    }

    #[Override()]
    public function soapParams(): array
    {
        return [
            'User' => [
                'Name' => 'John',
                'Email' => 'john@example.com',
            ],
            'Options' => [
                'Format' => 'xml',
            ],
        ];
    }
}

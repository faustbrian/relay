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
final class GetStockQuoteRequest extends SoapRequest
{
    protected string $soapVersion = '1.2';

    protected ?string $soapAction = 'http://example.com/GetQuote';

    public function wsdl(): ?string
    {
        return null;
    }

    public function endpoint(): string
    {
        return '/stock-service';
    }

    public function soapMethod(): string
    {
        return 'GetQuote';
    }

    #[Override()]
    public function soapParams(): array
    {
        return [
            'Symbol' => 'AAPL',
        ];
    }
}

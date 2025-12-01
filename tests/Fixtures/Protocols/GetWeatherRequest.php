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
final class GetWeatherRequest extends SoapRequest
{
    public function __construct(
        private readonly string $city,
    ) {}

    public function wsdl(): ?string
    {
        return null; // Non-WSDL mode for testing
    }

    public function endpoint(): string
    {
        return '/weather-service';
    }

    public function soapMethod(): string
    {
        return 'GetWeather';
    }

    #[Override()]
    public function soapParams(): array
    {
        return [
            'City' => $this->city,
        ];
    }
}

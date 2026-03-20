<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Tests\Fixtures\Protocols\Microservice\CreateUserData;
use Tests\Fixtures\Protocols\Microservice\CreateUserMicroserviceRequest;

describe('JsonRpcMicroserviceRequest', function (): void {
    it('wraps array payloads in a data envelope', function (): void {
        $request = new CreateUserMicroserviceRequest([
            'name' => 'John Doe',
            'email' => null,
        ]);

        expect($request->body()['params'])->toBe([
            'data' => [
                'name' => 'John Doe',
                'email' => null,
            ],
        ]);
    });

    it('preserves null values from data objects', function (): void {
        $request = new CreateUserMicroserviceRequest(
            new CreateUserData(
                name: 'John Doe',
                email: null,
            ),
        );

        expect($request->body()['params'])->toBe([
            'data' => [
                'name' => 'John Doe',
                'email' => null,
            ],
        ]);
    });
});

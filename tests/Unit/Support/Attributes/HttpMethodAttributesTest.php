<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Attributes\Methods\Delete;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Support\Attributes\Methods\Head;
use Cline\Relay\Support\Attributes\Methods\Options;
use Cline\Relay\Support\Attributes\Methods\Patch;
use Cline\Relay\Support\Attributes\Methods\Post;
use Cline\Relay\Support\Attributes\Methods\Put;

describe('HTTP Method Attributes', function (): void {
    it('Get returns GET method', function (): void {
        $attr = new Get();
        expect($attr->method())->toBe('GET');
    });

    it('Post returns POST method', function (): void {
        $attr = new Post();
        expect($attr->method())->toBe('POST');
    });

    it('Put returns PUT method', function (): void {
        $attr = new Put();
        expect($attr->method())->toBe('PUT');
    });

    it('Patch returns PATCH method', function (): void {
        $attr = new Patch();
        expect($attr->method())->toBe('PATCH');
    });

    it('Delete returns DELETE method', function (): void {
        $attr = new Delete();
        expect($attr->method())->toBe('DELETE');
    });

    it('Head returns HEAD method', function (): void {
        $attr = new Head();
        expect($attr->method())->toBe('HEAD');
    });

    it('Options returns OPTIONS method', function (): void {
        $attr = new Options();
        expect($attr->method())->toBe('OPTIONS');
    });
});

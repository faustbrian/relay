<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Attributes\Protocols\GraphQL;
use Cline\Relay\Support\Attributes\Protocols\JsonRpc;
use Cline\Relay\Support\Attributes\Protocols\Soap;
use Cline\Relay\Support\Attributes\Protocols\XmlRpc;

describe('Protocol Attributes', function (): void {
    describe('JsonRpc', function (): void {
        it('returns jsonrpc protocol', function (): void {
            $attr = new JsonRpc();
            expect($attr->protocol())->toBe('jsonrpc');
        });

        it('returns application/json content type', function (): void {
            $attr = new JsonRpc();
            expect($attr->defaultContentType())->toBe('application/json');
        });

        it('defaults to version 2.0', function (): void {
            $attr = new JsonRpc();
            expect($attr->version)->toBe('2.0');
        });
    });

    describe('XmlRpc', function (): void {
        it('returns xmlrpc protocol', function (): void {
            $attr = new XmlRpc();
            expect($attr->protocol())->toBe('xmlrpc');
        });

        it('returns text/xml content type', function (): void {
            $attr = new XmlRpc();
            expect($attr->defaultContentType())->toBe('text/xml');
        });
    });

    describe('Soap', function (): void {
        it('returns soap protocol', function (): void {
            $attr = new Soap();
            expect($attr->protocol())->toBe('soap');
        });

        it('returns text/xml content type for SOAP 1.1', function (): void {
            $attr = new Soap('1.1');
            expect($attr->defaultContentType())->toBe('text/xml');
        });

        it('returns application/soap+xml content type for SOAP 1.2', function (): void {
            $attr = new Soap('1.2');
            expect($attr->defaultContentType())->toBe('application/soap+xml');
        });
    });

    describe('GraphQL', function (): void {
        it('returns graphql protocol', function (): void {
            $attr = new GraphQL();
            expect($attr->protocol())->toBe('graphql');
        });

        it('returns application/json content type', function (): void {
            $attr = new GraphQL();
            expect($attr->defaultContentType())->toBe('application/json');
        });
    });
});

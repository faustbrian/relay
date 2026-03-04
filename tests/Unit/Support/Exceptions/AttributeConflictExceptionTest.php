<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Exceptions\AttributeConflictException;

describe('AttributeConflictException', function (): void {
    describe('Happy Paths', function (): void {
        test('creates exception for multiple HTTP methods with correct message', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['GET', 'POST']);

            // Assert
            expect($exception)->toBeInstanceOf(AttributeConflictException::class)
                ->and($exception->getMessage())->toContain('TestRequest')
                ->and($exception->getMessage())->toContain('GET, POST')
                ->and($exception->getMessage())->toContain('Only one is allowed');
        });

        test('creates exception for multiple content types with correct message', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleContentTypes('TestRequest', ['Json', 'Form']);

            // Assert
            expect($exception)->toBeInstanceOf(AttributeConflictException::class)
                ->and($exception->getMessage())->toContain('TestRequest')
                ->and($exception->getMessage())->toContain('Json, Form')
                ->and($exception->getMessage())->toContain('Only one is allowed');
        });

        test('creates exception for multiple protocols with correct message', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleProtocols('TestRequest', ['GraphQL', 'Soap']);

            // Assert
            expect($exception)->toBeInstanceOf(AttributeConflictException::class)
                ->and($exception->getMessage())->toContain('TestRequest')
                ->and($exception->getMessage())->toContain('GraphQL, Soap')
                ->and($exception->getMessage())->toContain('Only one is allowed');
        });

        test('creates exception for three conflicting HTTP methods', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('MyRequest', ['GET', 'POST', 'PUT']);

            // Assert
            expect($exception->getMessage())->toContain('GET, POST, PUT');
        });

        test('creates exception for three conflicting protocols', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleProtocols('MyRequest', ['REST', 'GraphQL', 'SOAP']);

            // Assert
            expect($exception->getMessage())->toContain('REST, GraphQL, SOAP');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles single HTTP method in array', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['GET']);

            // Assert
            expect($exception->getMessage())->toContain('GET');
        });

        test('handles empty array for HTTP methods', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', []);

            // Assert
            expect($exception->getMessage())->toContain('TestRequest');
        });

        test('handles fully qualified class name for request class', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods(
                'App\\Http\\Requests\\TestRequest',
                ['GET', 'POST'],
            );

            // Assert
            expect($exception->getMessage())->toContain('App\\Http\\Requests\\TestRequest');
        });

        test('handles numeric values in method names', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['HTTP1', 'HTTP2']);

            // Assert
            expect($exception->getMessage())->toContain('HTTP1, HTTP2');
        });

        test('handles special characters in request class name', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleContentTypes(
                'Test_Request_V2',
                ['Json', 'Xml'],
            );

            // Assert
            expect($exception->getMessage())->toContain('Test_Request_V2');
        });

        test('handles long list of conflicting attributes', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleContentTypes(
                'TestRequest',
                ['Json', 'Xml', 'Form', 'Multipart', 'Plain'],
            );

            // Assert
            expect($exception->getMessage())->toContain('Json, Xml, Form, Multipart, Plain');
        });

        test('handles mixed case in method names', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['get', 'POST', 'Put']);

            // Assert
            expect($exception->getMessage())->toContain('get, POST, Put');
        });

        test('handles protocol names with special characters', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleProtocols(
                'TestRequest',
                ['JSON-RPC', 'XML-RPC'],
            );

            // Assert
            expect($exception->getMessage())->toContain('JSON-RPC, XML-RPC');
        });

        test('creates runtime exception instance', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['GET', 'POST']);

            // Assert
            expect($exception)->toBeInstanceOf(RuntimeException::class);
        });

        test('exception message includes attribute type for HTTP methods', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleHttpMethods('TestRequest', ['GET', 'POST']);

            // Assert
            expect($exception->getMessage())->toContain('HTTP method attributes');
        });

        test('exception message includes attribute type for content types', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleContentTypes('TestRequest', ['Json', 'Xml']);

            // Assert
            expect($exception->getMessage())->toContain('content type attributes');
        });

        test('exception message includes attribute type for protocols', function (): void {
            // Arrange & Act
            $exception = AttributeConflictException::multipleProtocols('TestRequest', ['GraphQL', 'REST']);

            // Assert
            expect($exception->getMessage())->toContain('protocol attributes');
        });
    });
});

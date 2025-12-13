<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Support\Attributes\Dto;
use Cline\Relay\Support\Attributes\Pagination\SimplePagination;

describe('Dto', function (): void {
    describe('Happy Paths', function (): void {
        test('creates attribute with class parameter only', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class);

            // Assert
            expect($dto->class)->toBe(stdClass::class)
                ->and($dto->dataKey)->toBeNull();
        });

        test('creates attribute with class and dataKey parameters', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class, dataKey: 'data');

            // Assert
            expect($dto->class)->toBe(stdClass::class)
                ->and($dto->dataKey)->toBe('data');
        });

        test('stores full namespaced class name correctly', function (): void {
            // Arrange & Act
            $dto = new Dto(class: DateTime::class);

            // Assert
            expect($dto->class)->toBe(DateTime::class);
        });

        test('stores custom dataKey for nested response extraction', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class, dataKey: 'response.data.items');

            // Assert
            expect($dto->dataKey)->toBe('response.data.items');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles empty string dataKey', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class, dataKey: '');

            // Assert
            expect($dto->dataKey)->toBe('');
        });

        test('handles dataKey with special characters', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class, dataKey: 'data-items.list_v2');

            // Assert
            expect($dto->dataKey)->toBe('data-items.list_v2');
        });

        test('stores class string as provided without validation', function (): void {
            // Arrange & Act
            $dto = new Dto(class: 'App\\Models\\User');

            // Assert
            expect($dto->class)->toBe('App\\Models\\User');
        });

        test('handles dataKey with numeric components', function (): void {
            // Arrange & Act
            $dto = new Dto(class: stdClass::class, dataKey: 'data.0.items');

            // Assert
            expect($dto->dataKey)->toBe('data.0.items');
        });
    });
});

describe('SimplePagination', function (): void {
    describe('Happy Paths', function (): void {
        test('creates attribute with default values when no parameters provided', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination();

            // Assert
            expect($pagination->page)->toBe('page')
                ->and($pagination->perPage)->toBe('per_page')
                ->and($pagination->dataKey)->toBe('data')
                ->and($pagination->hasMoreKey)->toBe('meta.has_more');
        });

        test('creates attribute with all custom parameter values', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                page: 'p',
                perPage: 'limit',
                dataKey: 'items',
                hasMoreKey: 'next_page',
            );

            // Assert
            expect($pagination->page)->toBe('p')
                ->and($pagination->perPage)->toBe('limit')
                ->and($pagination->dataKey)->toBe('items')
                ->and($pagination->hasMoreKey)->toBe('next_page');
        });

        test('creates attribute with only page parameter customized', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(page: 'page_number');

            // Assert
            expect($pagination->page)->toBe('page_number')
                ->and($pagination->perPage)->toBe('per_page')
                ->and($pagination->dataKey)->toBe('data')
                ->and($pagination->hasMoreKey)->toBe('meta.has_more');
        });

        test('creates attribute with only perPage parameter customized', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(perPage: 'size');

            // Assert
            expect($pagination->perPage)->toBe('size')
                ->and($pagination->page)->toBe('page')
                ->and($pagination->dataKey)->toBe('data')
                ->and($pagination->hasMoreKey)->toBe('meta.has_more');
        });

        test('creates attribute with nested dataKey path', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(dataKey: 'response.data.items');

            // Assert
            expect($pagination->dataKey)->toBe('response.data.items');
        });

        test('creates attribute with nested hasMoreKey path', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(hasMoreKey: 'pagination.has_next');

            // Assert
            expect($pagination->hasMoreKey)->toBe('pagination.has_next');
        });
    });

    describe('Edge Cases', function (): void {
        test('handles single character parameter names', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(page: 'p', perPage: 'l');

            // Assert
            expect($pagination->page)->toBe('p')
                ->and($pagination->perPage)->toBe('l');
        });

        test('handles parameter names with underscores', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                page: 'current_page',
                perPage: 'items_per_page',
            );

            // Assert
            expect($pagination->page)->toBe('current_page')
                ->and($pagination->perPage)->toBe('items_per_page');
        });

        test('handles parameter names with hyphens', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                page: 'page-num',
                perPage: 'per-page',
            );

            // Assert
            expect($pagination->page)->toBe('page-num')
                ->and($pagination->perPage)->toBe('per-page');
        });

        test('handles deeply nested key paths', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                dataKey: 'response.body.data.items.list',
                hasMoreKey: 'response.body.meta.pagination.has_more_pages',
            );

            // Assert
            expect($pagination->dataKey)->toBe('response.body.data.items.list')
                ->and($pagination->hasMoreKey)->toBe('response.body.meta.pagination.has_more_pages');
        });

        test('handles empty string values for custom parameters', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                page: '',
                perPage: '',
                dataKey: '',
                hasMoreKey: '',
            );

            // Assert
            expect($pagination->page)->toBe('')
                ->and($pagination->perPage)->toBe('')
                ->and($pagination->dataKey)->toBe('')
                ->and($pagination->hasMoreKey)->toBe('');
        });

        test('handles numeric string parameter names', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(page: '1', perPage: '2');

            // Assert
            expect($pagination->page)->toBe('1')
                ->and($pagination->perPage)->toBe('2');
        });

        test('handles camelCase parameter names', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                page: 'currentPage',
                perPage: 'itemsPerPage',
            );

            // Assert
            expect($pagination->page)->toBe('currentPage')
                ->and($pagination->perPage)->toBe('itemsPerPage');
        });

        test('handles mixed case and special characters in key paths', function (): void {
            // Arrange & Act
            $pagination = new SimplePagination(
                dataKey: 'Data.Items_List',
                hasMoreKey: 'Meta.Has-More_Pages',
            );

            // Assert
            expect($pagination->dataKey)->toBe('Data.Items_List')
                ->and($pagination->hasMoreKey)->toBe('Meta.Has-More_Pages');
        });
    });
});

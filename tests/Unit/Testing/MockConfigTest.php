<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Testing\Fixture;
use Cline\Relay\Testing\MockConfig;

beforeEach(function (): void {
    MockConfig::reset();
});

afterEach(function (): void {
    MockConfig::reset();
});

describe('MockConfig preventStrayRequests', function (): void {
    it('is disabled by default', function (): void {
        expect(MockConfig::shouldPreventStrayRequests())->toBeFalse();
    });

    it('can be enabled', function (): void {
        MockConfig::preventStrayRequests();

        expect(MockConfig::shouldPreventStrayRequests())->toBeTrue();
    });

    it('can be enabled with explicit true', function (): void {
        MockConfig::preventStrayRequests(true);

        expect(MockConfig::shouldPreventStrayRequests())->toBeTrue();
    });

    it('can be disabled after enabling', function (): void {
        MockConfig::preventStrayRequests(true);
        MockConfig::preventStrayRequests(false);

        expect(MockConfig::shouldPreventStrayRequests())->toBeFalse();
    });
});

describe('MockConfig throwOnMissingFixtures', function (): void {
    it('is disabled by default', function (): void {
        expect(MockConfig::shouldThrowOnMissingFixtures())->toBeFalse();
    });

    it('can be enabled', function (): void {
        MockConfig::throwOnMissingFixtures();

        expect(MockConfig::shouldThrowOnMissingFixtures())->toBeTrue();
    });

    it('can be enabled with explicit true', function (): void {
        MockConfig::throwOnMissingFixtures(true);

        expect(MockConfig::shouldThrowOnMissingFixtures())->toBeTrue();
    });

    it('can be disabled after enabling', function (): void {
        MockConfig::throwOnMissingFixtures(true);
        MockConfig::throwOnMissingFixtures(false);

        expect(MockConfig::shouldThrowOnMissingFixtures())->toBeFalse();
    });
});

describe('MockConfig fixturePath', function (): void {
    it('has default fixture path', function (): void {
        expect(MockConfig::getFixturePath())->toBe('tests/Fixtures/Saloon');
    });

    it('can set custom fixture path', function (): void {
        MockConfig::setFixturePath('custom/fixtures/path');

        expect(MockConfig::getFixturePath())->toBe('custom/fixtures/path');
    });

    it('syncs fixture path with Fixture class', function (): void {
        MockConfig::setFixturePath('synced/path');

        expect(Fixture::getFixturePath())->toBe('synced/path');
    });
});

describe('MockConfig reset', function (): void {
    it('resets all configuration to defaults', function (): void {
        MockConfig::preventStrayRequests(true);
        MockConfig::throwOnMissingFixtures(true);
        MockConfig::setFixturePath('custom/path');

        MockConfig::reset();

        expect(MockConfig::shouldPreventStrayRequests())->toBeFalse();
        expect(MockConfig::shouldThrowOnMissingFixtures())->toBeFalse();
        expect(MockConfig::getFixturePath())->toBe('tests/Fixtures/Saloon');
    });

    it('resets Fixture path too', function (): void {
        MockConfig::setFixturePath('custom/path');
        MockConfig::reset();

        expect(Fixture::getFixturePath())->toBe('tests/Fixtures/Saloon');
    });
});

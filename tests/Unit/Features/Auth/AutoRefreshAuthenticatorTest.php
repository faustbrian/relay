<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Carbon\CarbonImmutable;
use Cline\Relay\Core\Request;
use Cline\Relay\Features\Auth\AccessTokenAuthenticator;
use Cline\Relay\Features\Auth\AutoRefreshAuthenticator;
use Cline\Relay\Support\Attributes\Methods\Get;
use Cline\Relay\Testing\MockConnector;
use Tests\Fixtures\Auth\RefreshableConnector;

describe('AutoRefreshAuthenticator', function (): void {
    it('authenticates request without refresh when token is valid', function (): void {
        $expiresAt = CarbonImmutable::now()->addHour();
        $authenticator = new AccessTokenAuthenticator(
            accessToken: 'valid-token',
            refreshToken: 'refresh-token',
            expiresAt: $expiresAt,
        );

        $connector = new MockConnector();
        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        $request = new #[Get()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        $authenticatedRequest = $autoRefresh->authenticate($request);

        expect($authenticatedRequest->allHeaders()['Authorization'])
            ->toBe('Bearer valid-token');
    });

    it('returns current authenticator', function (): void {
        $authenticator = new AccessTokenAuthenticator('token', 'refresh');
        $connector = new MockConnector();

        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        expect($autoRefresh->getAuthenticator())->toBe($authenticator);
    });

    it('proxies hasExpired to underlying authenticator', function (): void {
        $expiredAuth = new AccessTokenAuthenticator(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: CarbonImmutable::now()->subHour(),
        );
        $connector = new MockConnector();
        $autoRefresh = new AutoRefreshAuthenticator($connector, $expiredAuth);

        expect($autoRefresh->hasExpired())->toBeTrue();

        $validAuth = new AccessTokenAuthenticator(
            accessToken: 'token',
            refreshToken: 'refresh',
            expiresAt: CarbonImmutable::now()->addHour(),
        );
        $autoRefresh2 = new AutoRefreshAuthenticator($connector, $validAuth);

        expect($autoRefresh2->hasExpired())->toBeFalse();
    });

    it('proxies isRefreshable to underlying authenticator', function (): void {
        $refreshable = new AccessTokenAuthenticator('token', 'refresh');
        $connector = new MockConnector();
        $autoRefresh = new AutoRefreshAuthenticator($connector, $refreshable);

        expect($autoRefresh->isRefreshable())->toBeTrue();

        $notRefreshable = new AccessTokenAuthenticator('token');
        $autoRefresh2 = new AutoRefreshAuthenticator($connector, $notRefreshable);

        expect($autoRefresh2->isRefreshable())->toBeFalse();
    });

    it('proxies getAccessToken to underlying authenticator', function (): void {
        $authenticator = new AccessTokenAuthenticator('my-access-token');
        $connector = new MockConnector();
        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        expect($autoRefresh->getAccessToken())->toBe('my-access-token');
    });

    it('proxies getRefreshToken to underlying authenticator', function (): void {
        $authenticator = new AccessTokenAuthenticator('token', 'my-refresh-token');
        $connector = new MockConnector();
        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        expect($autoRefresh->getRefreshToken())->toBe('my-refresh-token');
    });

    it('does not refresh when token has no expiry', function (): void {
        $authenticator = new AccessTokenAuthenticator('token', 'refresh');
        $connector = new MockConnector();

        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        $request = new #[Get()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        // Should not throw since no refresh is needed
        $authenticatedRequest = $autoRefresh->authenticate($request);

        expect($authenticatedRequest->allHeaders()['Authorization'])
            ->toBe('Bearer token');
    });

    it('does not refresh when token is not refreshable', function (): void {
        $authenticator = new AccessTokenAuthenticator(
            accessToken: 'token',
            refreshToken: null,
            expiresAt: CarbonImmutable::now()->subHour(),
        );
        $connector = new MockConnector();

        $autoRefresh = new AutoRefreshAuthenticator($connector, $authenticator);

        $request = new #[Get()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        // Should not throw even though expired, because not refreshable
        $authenticatedRequest = $autoRefresh->authenticate($request);

        expect($authenticatedRequest->allHeaders()['Authorization'])
            ->toBe('Bearer token');
    });

    it('refreshes token when expired and refreshable', function (): void {
        $expiredAuth = new AccessTokenAuthenticator(
            accessToken: 'old-token',
            refreshToken: 'refresh-token',
            expiresAt: CarbonImmutable::now()->subHour(),
        );

        $newAuth = new AccessTokenAuthenticator(
            accessToken: 'new-token',
            refreshToken: 'new-refresh-token',
            expiresAt: CarbonImmutable::now()->addHour(),
        );

        $connector = new RefreshableConnector();
        $connector->newAuth = $newAuth;

        $autoRefresh = new AutoRefreshAuthenticator($connector, $expiredAuth);

        $request = new #[Get()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        $authenticatedRequest = $autoRefresh->authenticate($request);

        expect($authenticatedRequest->allHeaders()['Authorization'])
            ->toBe('Bearer new-token');
        expect($autoRefresh->getAuthenticator()->getAccessToken())
            ->toBe('new-token');
    });

    it('calls onRefresh callback after refresh', function (): void {
        $expiredAuth = new AccessTokenAuthenticator(
            accessToken: 'old-token',
            refreshToken: 'refresh-token',
            expiresAt: CarbonImmutable::now()->subHour(),
        );

        $newAuth = new AccessTokenAuthenticator(
            accessToken: 'refreshed-token',
            refreshToken: 'new-refresh',
            expiresAt: CarbonImmutable::now()->addHour(),
        );

        $connector = new RefreshableConnector();
        $connector->newAuth = $newAuth;

        $callbackCalled = false;
        $capturedAuth = null;

        $autoRefresh = new AutoRefreshAuthenticator(
            $connector,
            $expiredAuth,
            function ($auth) use (&$callbackCalled, &$capturedAuth): void {
                $callbackCalled = true;
                $capturedAuth = $auth;
            },
        );

        $request = new #[Get()] class() extends Request
        {
            public function endpoint(): string
            {
                return '/users';
            }
        };

        $autoRefresh->authenticate($request);

        expect($callbackCalled)->toBeTrue();
        expect($capturedAuth)->toBeInstanceOf(AccessTokenAuthenticator::class);
        expect($capturedAuth->getAccessToken())->toBe('refreshed-token');
    });
});

<?php declare(strict_types=1);

/**
 * Copyright (C) Brian Faust
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Cline\Relay\Core\Connector;
use Cline\Relay\Core\Request;
use Cline\Relay\Core\Resource;
use Cline\Relay\Core\Response;
use Cline\Relay\Testing\MockConnector;
use Cline\Relay\Testing\MockResponse;

describe('Resource', function (): void {
    it('sends requests through the connector', function (): void {
        $connector = new MockConnector();
        $connector->addResponse(MockResponse::json(['id' => 1, 'name' => 'Test']));

        $resource = new class($connector) extends Resource
        {
            public function get(int $id): Response
            {
                return $this->send(
                    new class($id) extends Request
                    {
                        public function __construct(
                            private readonly int $id,
                        ) {}

                        public function endpoint(): string
                        {
                            return '/items/'.$this->id;
                        }
                    },
                );
            }
        };

        $response = $resource->get(1);

        expect($response->json('id'))->toBe(1);
        expect($response->json('name'))->toBe('Test');

        $connector->assertSent('/items/1');
    });

    it('provides access to the connector', function (): void {
        $connector = new MockConnector();

        $resource = new class($connector) extends Resource
        {
            public function getConnector(): Connector
            {
                return $this->connector();
            }
        };

        expect($resource->getConnector())->toBe($connector);
    });

    it('groups related requests', function (): void {
        $connector = new MockConnector();
        $connector->addResponses([
            MockResponse::json(['users' => [['id' => 1], ['id' => 2]]]),
            MockResponse::json(['id' => 1, 'name' => 'John']),
            MockResponse::json(['id' => 2], 201),
        ]);

        $resource = new class($connector) extends Resource
        {
            public function list(): array
            {
                $response = $this->send(
                    new class() extends Request
                    {
                        public function endpoint(): string
                        {
                            return '/users';
                        }
                    },
                );

                return $response->json('users');
            }

            public function get(int $id): array
            {
                $response = $this->send(
                    new class($id) extends Request
                    {
                        public function __construct(
                            private readonly int $id,
                        ) {}

                        public function endpoint(): string
                        {
                            return '/users/'.$this->id;
                        }
                    },
                );

                return $response->json();
            }

            public function create(array $data): int
            {
                $response = $this->send(
                    new class($data) extends Request
                    {
                        public function __construct(
                            private readonly array $data,
                        ) {}

                        public function endpoint(): string
                        {
                            return '/users';
                        }

                        public function method(): string
                        {
                            return 'POST';
                        }

                        public function body(): array
                        {
                            return $this->data;
                        }
                    },
                );

                return $response->json('id');
            }
        };

        $users = $resource->list();
        expect($users)->toHaveCount(2);

        $user = $resource->get(1);
        expect($user['name'])->toBe('John');

        $id = $resource->create(['name' => 'Jane']);
        expect($id)->toBe(2);

        $connector->assertSentCount(3);
    });
});

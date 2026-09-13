<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\RabbitMqManagementQueueProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

final class RabbitMqManagementQueueProviderTest extends TestCase
{
    public function testItAcceptsAnEmptyQueueListWithNoPages(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'items' => [],
                'page_count' => 0,
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $queues = new RabbitMqManagementQueueProvider($client)->queues();

        $this->assertSame([], $queues);
    }

    public function testItMapsAndSortsQueuesFromEveryPage(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url, array $options) use (&$requests): MockResponse {
            $requests[] = [$method, $url, $options];

            if (str_contains($url, 'page=1')) {
                return new MockResponse(json_encode([
                    'items' => [[
                        'vhost' => 'billing',
                        'name' => 'imports',
                        'messages_ready' => 2841,
                        'messages_unacknowledged' => 16,
                        'consumers' => 8,
                        'messages' => 2857,
                        'type' => 'quorum',
                        'durable' => true,
                        'auto_delete' => false,
                        'exclusive' => false,
                        'state' => 'running',
                    ]],
                    'page_count' => 2,
                ], \JSON_THROW_ON_ERROR));
            }

            return new MockResponse(json_encode([
                'items' => [[
                    'vhost' => '/',
                    'name' => 'emails',
                    'messages_ready' => 0,
                    'messages_unacknowledged' => 2,
                    'messages' => 2,
                    'durable' => true,
                    'auto_delete' => false,
                    'exclusive' => false,
                ]],
                'page_count' => 2,
            ], \JSON_THROW_ON_ERROR));
        });

        $queues = new RabbitMqManagementQueueProvider($client)->queues();

        $this->assertSame('emails', $queues[0]->name);
        $this->assertSame('/', $queues[0]->vhost);
        $this->assertSame(0, $queues[0]->readyMessages);
        $this->assertSame(2, $queues[0]->unacknowledgedMessages);
        $this->assertNull($queues[0]->consumers);
        $this->assertSame(2, $queues[0]->totalMessages);
        $this->assertSame('classic', $queues[0]->type);
        $this->assertTrue($queues[0]->durable);
        $this->assertSame('imports', $queues[1]->name);
        $this->assertSame('billing', $queues[1]->vhost);
        $this->assertSame(8, $queues[1]->consumers);
        $this->assertSame(2857, $queues[1]->totalMessages);
        $this->assertSame('quorum', $queues[1]->type);
        $this->assertSame('running', $queues[1]->state);
        $this->assertCount(2, $requests);
        $this->assertSame('GET', $requests[0][0]);
        $this->assertStringContainsString('/api/queues?page=1&page_size=100&pagination=true', $requests[0][1]);
    }

    public function testItsRefreshDoesNotPublishAPartialPaginatedSnapshot(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'items' => [[
                    'vhost' => '/',
                    'name' => 'first',
                    'messages_ready' => 0,
                    'messages_unacknowledged' => 0,
                    'messages' => 0,
                    'durable' => true,
                    'auto_delete' => false,
                    'exclusive' => false,
                ]],
                'page_count' => 2,
            ], \JSON_THROW_ON_ERROR)),
            new MockResponse(json_encode([
                'items' => [[
                    'vhost' => '/',
                    'name' => 'second',
                    'messages_ready' => 0,
                    'messages_unacknowledged' => 0,
                    'messages' => 0,
                    'durable' => true,
                    'auto_delete' => false,
                    'exclusive' => false,
                ]],
                'page_count' => 2,
            ], \JSON_THROW_ON_ERROR)),
        ]);

        $refresh = new RabbitMqManagementQueueProvider($client)->startRefresh();

        $this->assertNull($refresh->advance(0.0));
        $queues = $refresh->advance(0.0);

        $this->assertSame(['first', 'second'], array_map(static fn ($queue) => $queue->name, $queues));
    }
}

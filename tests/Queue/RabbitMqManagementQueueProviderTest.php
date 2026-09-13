<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueSnapshot;
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

    public function testItMapsQueueDetailsRatesAndConsumers(): void
    {
        $requests = [];
        $client = new MockHttpClient(static function (string $method, string $url) use (&$requests): MockResponse {
            $requests[] = [$method, $url];

            return new MockResponse(json_encode([
                'vhost' => '/billing',
                'name' => 'invoice jobs',
                'messages_ready' => 12,
                'messages_unacknowledged' => 3,
                'consumers' => 1,
                'messages' => 15,
                'type' => 'quorum',
                'durable' => true,
                'auto_delete' => false,
                'exclusive' => false,
                'state' => 'running',
                'message_stats' => [
                    'publish_details' => ['rate' => 27.34],
                    'deliver_get_details' => ['rate' => 25],
                ],
                'consumer_details' => [[
                    'consumer_tag' => 'worker-1',
                    'prefetch_count' => 20,
                    'ack_required' => true,
                    'activity_status' => 'up',
                    'channel_details' => [
                        'name' => '127.0.0.1:40100 -> 127.0.0.1:5672 (1)',
                        'connection_name' => '127.0.0.1:40100 -> 127.0.0.1:5672',
                    ],
                ]],
            ], \JSON_THROW_ON_ERROR));
        });
        $queue = new QueueSnapshot('/billing', 'invoice jobs', 0, 0, 0, 0, 'classic', false, false, false, null);

        $detail = new RabbitMqManagementQueueProvider($client)->startDetailRefresh($queue)->advance(0.0);

        $this->assertNotNull($detail);
        $this->assertSame('quorum', $detail->queue->type);
        $this->assertSame(27.34, $detail->publishRate);
        $this->assertSame(25.0, $detail->deliveryRate);
        $this->assertCount(1, $detail->consumers);
        $this->assertSame('worker-1', $detail->consumers[0]->tag);
        $this->assertSame(20, $detail->consumers[0]->prefetchCount);
        $this->assertTrue($detail->consumers[0]->acknowledgementRequired);
        $this->assertSame('up', $detail->consumers[0]->activityStatus);
        $this->assertSame('GET', $requests[0][0]);
        $this->assertStringContainsString('/api/queues/%2Fbilling/invoice%20jobs', $requests[0][1]);
    }

    public function testItKeepsUnavailableRatesDistinctFromZeroRates(): void
    {
        $client = new MockHttpClient([
            new MockResponse(json_encode([
                'vhost' => '/',
                'name' => 'idle',
                'messages_ready' => 0,
                'messages_unacknowledged' => 0,
                'consumers' => 0,
                'messages' => 0,
                'durable' => true,
                'auto_delete' => false,
                'exclusive' => false,
                'message_stats' => ['publish_details' => ['rate' => 0]],
                'consumer_details' => [],
            ], \JSON_THROW_ON_ERROR)),
        ]);
        $queue = new QueueSnapshot('/', 'idle', 0, 0, 0, 0, 'classic', true, false, false, null);

        $detail = new RabbitMqManagementQueueProvider($client)->startDetailRefresh($queue)->advance(0.0);

        $this->assertNotNull($detail);
        $this->assertSame(0.0, $detail->publishRate);
        $this->assertNull($detail->deliveryRate);
        $this->assertSame([], $detail->consumers);
    }
}

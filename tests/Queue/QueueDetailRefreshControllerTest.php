<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueConsumerSnapshot;
use App\Queue\QueueDetailRefreshController;
use App\Queue\QueueDetailRefreshInterface;
use App\Queue\QueueDetailSnapshot;
use App\Queue\QueueProviderInterface;
use App\Queue\QueueRefreshInterface;
use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\ClientException;
use Symfony\Component\HttpClient\Response\MockResponse;

final class QueueDetailRefreshControllerTest extends TestCase
{
    public function testItStartsImmediatelyAndDoesNotOverlapDetailRefreshes(): void
    {
        $queue = $this->queue(1);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $refresh = new StubQueueDetailRefresh([null, $this->detail(2)]);
        $provider = new StubDetailQueueProvider($refresh);
        $controller = new QueueDetailRefreshController($provider, $state);
        $controller->inspect($queue, 10.0);

        $controller->tick(10.0);
        $controller->tick(11.0);

        $this->assertSame(1, $provider->startedRefreshes);
        $this->assertSame(2, $refresh->advanceCalls);
        $this->assertSame(2, $state->queueDetail()?->queue->readyMessages);
        $this->assertFalse($controller->isRefreshing());
    }

    public function testItKeepsTheLastDetailSnapshotAfterARefreshFailure(): void
    {
        $queue = $this->queue(1);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $state->replaceQueueDetail($this->detail(1));
        $refresh = new StubQueueDetailRefresh([new \UnexpectedValueException('invalid consumer')]);
        $controller = new QueueDetailRefreshController(new StubDetailQueueProvider($refresh), $state);
        $controller->inspect($queue, 10.0);

        $changed = $controller->tick(10.0);

        $this->assertTrue($changed);
        $this->assertSame(1, $state->queueDetail()?->queue->readyMessages);
        $this->assertSame('Detail refresh failed: invalid consumer', $state->status());
        $this->assertSame(1, $refresh->cancelCalls);
    }

    public function testItReplacesQueueFieldsAndConsumersTogether(): void
    {
        $queue = $this->queue(1);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $first = $this->detail(1, 'first');
        $state->replaceQueueDetail($first);
        $second = $this->detail(42, 'second');
        $controller = new QueueDetailRefreshController(new StubDetailQueueProvider(new StubQueueDetailRefresh([$second])), $state);
        $controller->inspect($queue, 10.0);

        $controller->tick(10.0);

        $this->assertSame($second, $state->queueDetail());
        $this->assertSame(42, $state->queueDetail()?->queue->readyMessages);
        $this->assertSame('second', $state->queueDetail()?->consumers[0]->tag);
    }

    public function testItCancelsAnActiveRefreshAfterLeavingDetails(): void
    {
        $queue = $this->queue(1);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $refresh = new StubQueueDetailRefresh([null]);
        $controller = new QueueDetailRefreshController(new StubDetailQueueProvider($refresh), $state);
        $controller->inspect($queue, 10.0);
        $controller->tick(10.0);

        $state->returnToOverview();
        $controller->tick(10.1);

        $this->assertSame(1, $refresh->cancelCalls);
        $this->assertFalse($controller->isRefreshing());
    }

    public function testItReturnsToOverviewWhenTheInspectedQueueReturnsNotFound(): void
    {
        $queue = $this->queue(1);
        $otherQueue = new QueueSnapshot('/', 'other', 0, 0, 0, 0, 'classic', true, false, false, 'running');
        $state = new QueueViewState([$queue, $otherQueue]);
        $state->openSelectedQueue();
        $refresh = new StubQueueDetailRefresh([
            new ClientException(new MockResponse('', ['http_code' => 404, 'url' => 'https://rabbitmq.test/api/queues/%2F/queue'])),
        ]);
        $controller = new QueueDetailRefreshController(new StubDetailQueueProvider($refresh), $state);
        $controller->inspect($queue, 10.0);

        $changed = $controller->tick(10.0);

        $this->assertTrue($changed);
        $this->assertFalse($state->showingDetail());
        $this->assertSame('Inspected queue disappeared.', $state->status());
        $this->assertSame([$otherQueue], $state->queues());
        $this->assertSame(1, $refresh->cancelCalls);
    }

    public function testItKeepsOtherHttpErrorsAsDetailRefreshFailures(): void
    {
        $queue = $this->queue(1);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $refresh = new StubQueueDetailRefresh([
            new ClientException(new MockResponse('', ['http_code' => 400, 'url' => 'https://rabbitmq.test/api/queues/%2F/queue'])),
        ]);
        $controller = new QueueDetailRefreshController(new StubDetailQueueProvider($refresh), $state);
        $controller->inspect($queue, 10.0);

        $controller->tick(10.0);

        $this->assertTrue($state->showingDetail());
        $this->assertStringContainsString('Detail refresh failed:', $state->status());
        $this->assertSame([$queue], $state->queues());
    }

    private function queue(int $readyMessages): QueueSnapshot
    {
        return new QueueSnapshot('/', 'queue', $readyMessages, 0, 1, $readyMessages, 'classic', true, false, false, 'running');
    }

    private function detail(int $readyMessages, string $tag = 'worker'): QueueDetailSnapshot
    {
        return new QueueDetailSnapshot($this->queue($readyMessages), 1.2, 1.0, [
            new QueueConsumerSnapshot($tag, 'channel', 'connection', 10, true, 'up'),
        ]);
    }
}

final class StubDetailQueueProvider implements QueueProviderInterface
{
    public int $startedRefreshes = 0;

    /** @var list<QueueDetailRefreshInterface> */
    private array $refreshes;

    public function __construct(QueueDetailRefreshInterface ...$refreshes)
    {
        $this->refreshes = $refreshes;
    }

    public function queues(): array
    {
        return [];
    }

    public function startRefresh(): QueueRefreshInterface
    {
        throw new \LogicException('Unexpected queue refresh.');
    }

    public function startDetailRefresh(QueueSnapshot $queue): QueueDetailRefreshInterface
    {
        ++$this->startedRefreshes;

        return array_shift($this->refreshes) ?? throw new \LogicException('Unexpected detail refresh.');
    }
}

final class StubQueueDetailRefresh implements QueueDetailRefreshInterface
{
    public int $advanceCalls = 0;
    public int $cancelCalls = 0;

    /** @param list<QueueDetailSnapshot|\Throwable|null> $results */
    public function __construct(private array $results)
    {
    }

    public function advance(?float $timeout = 0.0): ?QueueDetailSnapshot
    {
        ++$this->advanceCalls;
        $result = array_shift($this->results);
        if ($result instanceof \Throwable) {
            throw $result;
        }

        return $result;
    }

    public function cancel(): void
    {
        ++$this->cancelCalls;
    }
}

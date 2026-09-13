<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueProviderInterface;
use App\Queue\QueueRefreshController;
use App\Queue\QueueRefreshInterface;
use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
use PHPUnit\Framework\TestCase;

final class QueueRefreshControllerTest extends TestCase
{
    public function testItDoesNotStartOverlappingRefreshes(): void
    {
        $refresh = new StubQueueRefresh([null, [$this->queue('queue', 2)]]);
        $provider = new StubQueueProvider($refresh);
        $controller = new QueueRefreshController($provider, new QueueViewState([$this->queue('queue', 1)]), 0.0);

        $controller->tick(2.0);
        $controller->tick(4.0);

        $this->assertSame(1, $provider->startedRefreshes);
        $this->assertSame(2, $refresh->advanceCalls);
        $this->assertFalse($controller->isRefreshing());
    }

    public function testItReconcilesTheSelectionThatExistsWhenTheRefreshCompletes(): void
    {
        $first = $this->queue('first', 1);
        $second = $this->queue('second', 1);
        $state = new QueueViewState([$first, $second]);
        $refresh = new StubQueueRefresh([null, [
            $this->queue('second', 2),
            $this->queue('first', 2),
        ]]);
        $controller = new QueueRefreshController(new StubQueueProvider($refresh), $state, 0.0);

        $controller->tick(2.0);
        $state->moveDown();
        $controller->tick(2.1);

        $this->assertSame('second', $state->selectedQueue()?->name);
        $this->assertSame(0, $state->selectedIndex());
    }

    public function testItPreservesTheLastSnapshotAndSelectionAfterARefreshFailure(): void
    {
        $queue = $this->queue('queue', 1);
        $state = new QueueViewState([$queue]);
        $refresh = new StubQueueRefresh([new \UnexpectedValueException('invalid JSON')]);
        $controller = new QueueRefreshController(new StubQueueProvider($refresh), $state, 0.0);

        $changed = $controller->tick(2.0);

        $this->assertTrue($changed);
        $this->assertSame($queue, $state->selectedQueue());
        $this->assertSame('Refresh failed: invalid JSON', $state->status());
        $this->assertSame(1, $refresh->cancelCalls);
    }

    public function testItClearsARefreshFailureAfterTheNextSuccessfulSnapshot(): void
    {
        $state = new QueueViewState([$this->queue('queue', 1)]);
        $failure = new StubQueueRefresh([new \UnexpectedValueException('invalid JSON')]);
        $success = new StubQueueRefresh([[$this->queue('queue', 2)]]);
        $controller = new QueueRefreshController(new StubQueueProvider($failure, $success), $state, 0.0);

        $controller->tick(2.0);
        $controller->tick(4.0);

        $this->assertNull($state->status());
        $this->assertSame(2, $state->selectedQueue()?->readyMessages);
    }

    public function testItAppliesTheFilterAfterReplacingTheCompleteSnapshot(): void
    {
        $state = new QueueViewState([$this->queue('matching', 1), $this->queue('other', 1)]);
        $state->setFilter('match');
        $refresh = new StubQueueRefresh([[$this->queue('matching', 2), $this->queue('other', 3)]]);
        $controller = new QueueRefreshController(new StubQueueProvider($refresh), $state, 0.0);

        $controller->tick(2.0);

        $this->assertSame(['matching'], array_map(static fn (QueueSnapshot $queue): string => $queue->name, $state->filteredQueues()));
        $this->assertSame(2, $state->filteredQueues()[0]->readyMessages);
        $this->assertSame(3, $state->queues()[1]->readyMessages);
    }

    private function queue(string $name, int $readyMessages): QueueSnapshot
    {
        return new QueueSnapshot('/', $name, $readyMessages, 0, 0, $readyMessages, 'classic', true, false, false, 'running');
    }
}

final class StubQueueProvider implements QueueProviderInterface
{
    public int $startedRefreshes = 0;

    public function __construct(QueueRefreshInterface ...$refreshes)
    {
        $this->refreshes = $refreshes;
    }

    /** @var list<QueueRefreshInterface> */
    private array $refreshes;

    public function queues(): array
    {
        return [];
    }

    public function startRefresh(): QueueRefreshInterface
    {
        ++$this->startedRefreshes;

        return array_shift($this->refreshes) ?? throw new \LogicException('Unexpected refresh.');
    }
}

final class StubQueueRefresh implements QueueRefreshInterface
{
    public int $advanceCalls = 0;
    public int $cancelCalls = 0;

    /** @param list<list<QueueSnapshot>|\Throwable|null> $results */
    public function __construct(private array $results)
    {
    }

    public function advance(?float $timeout = 0.0): ?array
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

<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueDetailSnapshot;
use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class QueueViewStateTest extends TestCase
{
    public function testItKeepsAnEmptyQueueListOnTheOverview(): void
    {
        $state = new QueueViewState([]);
        $state->moveDown();
        $state->openSelectedQueue();

        $this->assertSame(0, $state->selectedIndex());
        $this->assertNull($state->selectedQueue());
        $this->assertFalse($state->showingDetail());
    }

    public function testItWrapsSelectionAndPreservesItWhenReturningFromDetails(): void
    {
        $first = $this->queue('first');
        $second = $this->queue('second');
        $state = new QueueViewState([$first, $second]);

        $state->moveUp();
        $this->assertSame(1, $state->selectedIndex());
        $state->moveDown();
        $this->assertSame(0, $state->selectedIndex());
        $state->openSelectedQueue();
        $state->returnToOverview();

        $this->assertSame(0, $state->selectedIndex());
        $this->assertFalse($state->showingDetail());
    }

    public function testItOpensTheOnlyAvailableQueue(): void
    {
        $queue = $this->queue('only');
        $state = new QueueViewState([$queue]);
        $state->moveDown();
        $state->openSelectedQueue();

        $this->assertSame(0, $state->selectedIndex());
        $this->assertSame($queue, $state->selectedQueue());
        $this->assertTrue($state->showingDetail());
    }

    public function testItKeepsTheSameQueueSelectedWhenItMoves(): void
    {
        $first = $this->queue('first');
        $selected = $this->queue('selected');
        $state = new QueueViewState([$first, $selected]);
        $state->moveDown();

        $state->replaceQueues([$selected, $first]);

        $this->assertSame(0, $state->selectedIndex());
        $this->assertSame('selected', $state->selectedQueue()?->name);
    }

    public function testItUsesBothVirtualHostAndNameToReconcileSelection(): void
    {
        $first = new QueueSnapshot('first', 'jobs', 0, 0, 0, 0, 'classic', true, false, false, 'running');
        $second = new QueueSnapshot('second', 'jobs', 0, 0, 0, 0, 'classic', true, false, false, 'running');
        $state = new QueueViewState([$first, $second]);
        $state->moveDown();

        $state->replaceQueues([$second, $first]);

        $this->assertSame(0, $state->selectedIndex());
        $this->assertSame('second', $state->selectedQueue()?->vhost);
    }

    #[DataProvider('selectedQueueRemoval')]
    public function testItSelectsTheNearestQueueWhenTheSelectedQueueDisappears(int $selectedIndex, int $expectedIndex, string $expectedName): void
    {
        $state = new QueueViewState([$this->queue('first'), $this->queue('second'), $this->queue('third')]);
        while ($state->selectedIndex() < $selectedIndex) {
            $state->moveDown();
        }

        $queues = $state->queues();
        array_splice($queues, $selectedIndex, 1);
        $state->replaceQueues($queues);

        $this->assertSame($expectedIndex, $state->selectedIndex());
        $this->assertSame($expectedName, $state->selectedQueue()?->name);
    }

    /** @return iterable<string, array{int, int, string}> */
    public static function selectedQueueRemoval(): iterable
    {
        yield 'first' => [0, 0, 'second'];
        yield 'middle' => [1, 1, 'third'];
        yield 'last' => [2, 1, 'second'];
    }

    public function testItReturnsFromDetailsWhenTheInspectedQueueDisappears(): void
    {
        $state = new QueueViewState([$this->queue('inspected')]);
        $state->openSelectedQueue();

        $returnedToOverview = $state->replaceQueues([]);

        $this->assertTrue($returnedToOverview);
        $this->assertFalse($state->showingDetail());
        $this->assertNull($state->selectedQueue());
        $this->assertSame('Inspected queue disappeared.', $state->status());
    }

    public function testItReplacesTheSelectionWithAnEmptySuccessfulSnapshot(): void
    {
        $state = new QueueViewState([$this->queue('queue')]);

        $state->replaceQueues([]);

        $this->assertSame([], $state->queues());
        $this->assertSame(0, $state->selectedIndex());
        $this->assertNull($state->selectedQueue());
    }

    public function testItFiltersByQueueNameAndVirtualHostWithoutCaseSensitivity(): void
    {
        $state = new QueueViewState([
            new QueueSnapshot('Billing', 'invoices', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('other', 'IMPORTS', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('Équipe', 'archive', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
        ]);

        $state->setFilter('port');
        $this->assertSame(['IMPORTS'], array_map(static fn (QueueSnapshot $queue): string => $queue->name, $state->filteredQueues()));

        $state->setFilter('bill');
        $this->assertSame(['invoices'], array_map(static fn (QueueSnapshot $queue): string => $queue->name, $state->filteredQueues()));

        $state->setFilter('éq');
        $this->assertSame(['archive'], array_map(static fn (QueueSnapshot $queue): string => $queue->name, $state->filteredQueues()));
    }

    public function testItKeepsAllQueuesForAnEmptyFilterAndCanHaveNoMatchingQueues(): void
    {
        $state = new QueueViewState([$this->queue('jobs')]);

        $this->assertSame(['jobs'], array_map(static fn (QueueSnapshot $queue): string => $queue->name, $state->filteredQueues()));

        $state->setFilter('missing');

        $this->assertSame([], $state->filteredQueues());
        $this->assertNull($state->selectedFilteredIndex());
    }

    public function testItSelectsTheNearestVisibleQueueWhenTheFilterHidesTheSelection(): void
    {
        $state = new QueueViewState([$this->queue('first'), $this->queue('selected'), $this->queue('third')]);
        $state->moveDown();

        $state->setFilter('third');

        $this->assertSame('third', $state->selectedQueue()?->name);
        $this->assertSame(0, $state->selectedFilteredIndex());
    }

    public function testItKeepsTheVisibleSelectionWhenClearingTheFilter(): void
    {
        $state = new QueueViewState([$this->queue('first'), $this->queue('selected'), $this->queue('third')]);
        $state->moveDown();
        $state->setFilter('third');

        $state->setFilter('');

        $this->assertSame('third', $state->selectedQueue()?->name);
        $this->assertSame(2, $state->selectedIndex());
        $this->assertCount(3, $state->filteredQueues());
    }

    public function testItNavigatesOnlyMatchingQueues(): void
    {
        $state = new QueueViewState([$this->queue('match-first'), $this->queue('skip'), $this->queue('match-last')]);
        $state->setFilter('match');

        $state->moveDown();
        $this->assertSame('match-last', $state->selectedQueue()?->name);

        $state->moveDown();
        $this->assertSame('match-first', $state->selectedQueue()?->name);
    }

    public function testItDoesNotCarryDetailDataOrFailureToAnotherQueue(): void
    {
        $first = $this->queue('first');
        $state = new QueueViewState([$first, $this->queue('second')]);
        $state->openSelectedQueue();
        $state->replaceQueueDetail(new QueueDetailSnapshot($first, 1.0, 1.0, []));
        $state->setDetailRefreshFailure('Detail refresh failed: unavailable');
        $state->returnToOverview();
        $state->moveDown();

        $state->openSelectedQueue();

        $this->assertNull($state->queueDetail());
        $this->assertNull($state->status());
    }

    private function queue(string $name): QueueSnapshot
    {
        return new QueueSnapshot('/', $name, 0, 0, 0, 0, 'classic', true, false, false, 'running');
    }
}

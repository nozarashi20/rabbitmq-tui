<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
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

    private function queue(string $name): QueueSnapshot
    {
        return new QueueSnapshot('/', $name, 0, 0, 0, 0, 'classic', true, false, false, 'running');
    }
}

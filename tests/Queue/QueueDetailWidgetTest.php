<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueConsumerSnapshot;
use App\Queue\QueueDetailRenderer;
use App\Queue\QueueDetailSnapshot;
use App\Queue\QueueDetailWidget;
use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class QueueDetailWidgetTest extends TestCase
{
    public function testItRendersUsingTheCurrentWidthAfterAResize(): void
    {
        $widget = new QueueDetailWidget(
            new QueueViewState([new QueueSnapshot('/', 'a-very-long-queue-name', 0, 0, 0, 0, 'classic', true, false, false, 'running')]),
            new QueueDetailRenderer(),
        );

        $narrow = $widget->render(new RenderContext(12, 20));
        $wide = $widget->render(new RenderContext(40, 20));

        $this->assertStringNotContainsString('queue-name', $narrow[0]);
        $this->assertSame('Queue: a-very-long-queue-name', $wide[0]);
        foreach ($wide as $line) {
            $this->assertLessThanOrEqual(40, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItLimitsDetailsToItsAllocatedRows(): void
    {
        $widget = new QueueDetailWidget(
            new QueueViewState([new QueueSnapshot('/', 'queue', 0, 0, 0, 0, 'classic', true, false, false, 'running')]),
            new QueueDetailRenderer(),
        )->expandVertically(true);

        $lines = $widget->render(new RenderContext(40, 2));

        $this->assertTrue($widget->isVerticallyExpanded());
        $this->assertCount(2, $lines);
        $this->assertSame('Queue: queue', $lines[0]);
    }

    public function testItRendersTheRefreshedQueueSnapshot(): void
    {
        $state = new QueueViewState([$this->queue(1)]);
        $widget = new QueueDetailWidget($state, new QueueDetailRenderer());

        $state->replaceQueues([$this->queue(42)]);

        $lines = $widget->render(new RenderContext(40, 20));

        $this->assertContains('Ready           42', $lines);
    }

    public function testItKeepsConsumerTroubleshootingDataVisibleInAShortViewport(): void
    {
        $queue = $this->queue(12);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $state->replaceQueueDetail(new QueueDetailSnapshot($queue, 2.5, 2.0, [
            new QueueConsumerSnapshot('worker', 'channel-1', 'connection-1', 20, true, 'up'),
        ]));
        $widget = new QueueDetailWidget($state, new QueueDetailRenderer());

        $lines = $widget->render(new RenderContext(24, 8));

        $this->assertCount(8, $lines);
        $this->assertContains('Consumers', $lines);
        $this->assertContains('1. worker', $lines);
        $this->assertContains('Ch channel-1', $lines);
        $this->assertContains('Prefetch 20  Ack yes  up', $lines);
    }

    public function testItScrollsToConsumersBeyondTheConstrainedViewport(): void
    {
        $queue = $this->queue(12);
        $state = new QueueViewState([$queue]);
        $state->openSelectedQueue();
        $state->replaceQueueDetail(new QueueDetailSnapshot($queue, 2.5, 2.0, [
            new QueueConsumerSnapshot('worker-1', 'channel-1', 'connection-1', 20, true, 'up'),
            new QueueConsumerSnapshot('worker-2', 'channel-2', 'connection-2', 20, true, 'up'),
            new QueueConsumerSnapshot('worker-3', 'channel-3', 'connection-3', 20, true, 'up'),
        ]));
        $widget = new QueueDetailWidget($state, new QueueDetailRenderer());

        $widget->render(new RenderContext(24, 8));
        $widget->handleInput("\e[6~");
        $lines = $widget->render(new RenderContext(24, 8));

        $this->assertContains('3. worker-3', $lines);
        $this->assertNotContains('1. worker-1', $lines);
    }

    private function queue(int $readyMessages): QueueSnapshot
    {
        return new QueueSnapshot('/', 'queue', $readyMessages, 0, 0, $readyMessages, 'classic', true, false, false, 'running');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueDetailRenderer;
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

    private function queue(int $readyMessages): QueueSnapshot
    {
        return new QueueSnapshot('/', 'queue', $readyMessages, 0, 0, $readyMessages, 'classic', true, false, false, 'running');
    }
}

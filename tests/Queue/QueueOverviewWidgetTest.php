<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueOverviewRenderer;
use App\Queue\QueueOverviewWidget;
use App\Queue\QueueSnapshot;
use App\Queue\QueueViewState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class QueueOverviewWidgetTest extends TestCase
{
    public function testItUsesTheAllocatedRowsAndKeepsTheSelectionVisible(): void
    {
        $queues = array_map(
            static fn (int $index): QueueSnapshot => new QueueSnapshot('/', 'queue-' . $index, 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            range(0, 4),
        );
        $state = new QueueViewState($queues);
        $state->moveUp();
        $widget = new QueueOverviewWidget($state, new QueueOverviewRenderer(), static function (): void {});

        $lines = $widget->render(new RenderContext(50, 3));

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('queue-4', $lines[2]);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(50, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItOmitsTheHeaderWhenOnlyOneRowIsAvailable(): void
    {
        $state = new QueueViewState([
            new QueueSnapshot('/', 'first', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('/', 'selected', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
        ]);
        $state->moveDown();
        $widget = new QueueOverviewWidget($state, new QueueOverviewRenderer(), static function (): void {})->expandVertically(true);

        $lines = $widget->render(new RenderContext(50, 1));

        $this->assertTrue($widget->isVerticallyExpanded());
        $this->assertCount(1, $lines);
        $this->assertStringContainsString('selected', $lines[0]);
        $this->assertStringNotContainsString('Name', $lines[0]);
    }

    public function testItKeepsTheFirstSelectionVisibleInAShortViewport(): void
    {
        $state = new QueueViewState([
            new QueueSnapshot('/', 'first', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('/', 'second', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('/', 'third', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
        ]);
        $widget = new QueueOverviewWidget($state, new QueueOverviewRenderer(), static function (): void {});

        $lines = $widget->render(new RenderContext(50, 2));

        $this->assertCount(2, $lines);
        $this->assertStringContainsString('first', $lines[1]);
    }
}

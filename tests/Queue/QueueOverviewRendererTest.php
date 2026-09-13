<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueOverviewRenderer;
use App\Queue\QueueSnapshot;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;

final class QueueOverviewRendererTest extends TestCase
{
    public function testItRendersQueueMetricsWithinTheAvailableWidth(): void
    {
        $lines = new QueueOverviewRenderer()->render([
            new QueueSnapshot('/', 'imports', 2841, 16, 8, 2857, 'classic', true, false, false, 'running'),
            new QueueSnapshot('billing', "emails\x1b]0;bad\x07", 0, 2, null, 2, 'classic', true, false, false, null),
        ], 50);

        $this->assertSame('Name            Vhost      Ready  Unack Consumers', $lines[0]);
        $this->assertStringContainsString('billing', $lines[2]);
        $this->assertStringContainsString('emails]0;bad', $lines[2]);
        $this->assertStringNotContainsString("\x1b", $lines[2]);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(50, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItUsesACompactLayoutInNarrowTerminals(): void
    {
        $lines = new QueueOverviewRenderer()->render([
            new QueueSnapshot('/', 'very-long-queue-name', 2841, 16, 8, 2857, 'classic', true, false, false, 'running'),
        ], 18);

        $this->assertSame(['/ · very-long-queu'], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
    }

    public function testItChangesLayoutAtTheDefinedWidths(): void
    {
        $renderer = new QueueOverviewRenderer();
        $queues = [new QueueSnapshot('/', 'imports', 2841, 16, 8, 2857, 'classic', true, false, false, 'running')];

        $atCompactLayout = $renderer->render($queues, 19);
        $beforeOverviewLayout = $renderer->render($queues, 31);
        $atOverviewLayout = $renderer->render($queues, 32);
        $beforeVhostLayout = $renderer->render($queues, 47);
        $atVhostLayout = $renderer->render($queues, 48);

        $this->assertSame('Name   Ready  Unack', $atCompactLayout[0]);
        $this->assertStringNotContainsString('Consumers', $beforeOverviewLayout[0]);
        $this->assertStringContainsString('Consumers', $atOverviewLayout[0]);
        $this->assertStringNotContainsString('Vhost', $beforeVhostLayout[0]);
        $this->assertStringContainsString('Vhost', $atVhostLayout[0]);
        foreach ([19 => $atCompactLayout, 31 => $beforeOverviewLayout, 32 => $atOverviewLayout, 47 => $beforeVhostLayout, 48 => $atVhostLayout] as $columns => $lines) {
            foreach ($lines as $line) {
                $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
            }
        }
    }

    public function testItMarksMetricsThatDoNotFit(): void
    {
        $lines = new QueueOverviewRenderer()->render([
            new QueueSnapshot('/', 'imports', 100000, 1000000, 1000000000, 1100000, 'classic', true, false, false, 'running'),
        ], 50);

        $this->assertStringContainsString('1000…', $lines[1]);
        $this->assertStringContainsString('10000…', $lines[1]);
        $this->assertStringContainsString('10000000…', $lines[1]);
        $this->assertLessThanOrEqual(50, AnsiUtils::visibleWidth($lines[1]));
    }

    public function testItKeepsTheSelectedQueueInTheViewport(): void
    {
        $queues = [];
        for ($index = 0; $index < 12; ++$index) {
            $queues[] = new QueueSnapshot('/', 'queue-' . $index, 0, 0, 0, 0, 'classic', true, false, false, 'running');
        }

        $lines = new QueueOverviewRenderer()->render($queues, 50, 10, 3);

        $this->assertCount(3, $lines);
        $this->assertStringContainsString('queue-10', $lines[2]);
    }

    #[DataProvider('narrowSelectedWidths')]
    public function testItKeepsSelectedRowsWithinVeryNarrowWidths(int $columns): void
    {
        $lines = new QueueOverviewRenderer()->render([
            new QueueSnapshot('/', 'first', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
            new QueueSnapshot('/', 'second', 0, 0, 0, 0, 'classic', true, false, false, 'running'),
        ], $columns, 1, 2);

        $this->assertCount(2, $lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual($columns, AnsiUtils::visibleWidth($line));
        }
        $this->assertSame($columns, AnsiUtils::visibleWidth($lines[1]));
    }

    /** @return iterable<string, array{int}> */
    public static function narrowSelectedWidths(): iterable
    {
        yield 'one column' => [1];
        yield 'two columns' => [2];
        yield 'three columns' => [3];
    }
}

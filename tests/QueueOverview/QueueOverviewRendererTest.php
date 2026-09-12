<?php

declare(strict_types=1);

namespace App\Tests\QueueOverview;

use App\QueueOverview\Queue;
use App\QueueOverview\QueueOverviewRenderer;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;

final class QueueOverviewRendererTest extends TestCase
{
    public function testItRendersQueueMetricsWithinTheAvailableWidth(): void
    {
        $lines = (new QueueOverviewRenderer())->render([
            new Queue('/', 'imports', 2841, 16, 8),
            new Queue('billing', "emails\x1b]0;bad\x07", 0, 2, null),
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
        $lines = (new QueueOverviewRenderer())->render([
            new Queue('/', 'very-long-queue-name', 2841, 16, 8),
        ], 18);

        $this->assertSame(['/ · very-long-queu'], array_map(AnsiUtils::stripAnsiCodes(...), $lines));
    }

    public function testItChangesLayoutAtTheDefinedWidths(): void
    {
        $renderer = new QueueOverviewRenderer();
        $queues = [new Queue('/', 'imports', 2841, 16, 8)];

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
        $lines = (new QueueOverviewRenderer())->render([
            new Queue('/', 'imports', 100000, 1000000, 1000000000),
        ], 50);

        $this->assertStringContainsString('1000…', $lines[1]);
        $this->assertStringContainsString('10000…', $lines[1]);
        $this->assertStringContainsString('10000000…', $lines[1]);
        $this->assertLessThanOrEqual(50, AnsiUtils::visibleWidth($lines[1]));
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueConsumerSnapshot;
use App\Queue\QueueDetailRenderer;
use App\Queue\QueueDetailSnapshot;
use App\Queue\QueueSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;

final class QueueDetailRendererTest extends TestCase
{
    public function testItRendersStableQueueDetailsWithinTheAvailableWidth(): void
    {
        $lines = new QueueDetailRenderer()->render(new QueueSnapshot('/', "jobs\x1b]0;bad\x07", 12, 3, null, 15, 'quorum', true, false, true, 'running'), null, 30);

        $this->assertSame('Queue: jobs]0;bad', $lines[0]);
        $this->assertContains('Type            quorum', $lines);
        $this->assertContains('State           running', $lines);
        $this->assertContains('Consumers       -', $lines);
        $this->assertContains('Exclusive       yes', $lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(30, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItRendersRatesAndConsumersFromOneDetailSnapshot(): void
    {
        $queue = new QueueSnapshot('/', 'jobs', 12, 3, 1, 15, 'quorum', true, false, false, 'running');
        $detail = new QueueDetailSnapshot($queue, 27.34, 25.06, [
            new QueueConsumerSnapshot(
                "worker\x1b]0;bad\x07",
                '127.0.0.1:40100 -> 127.0.0.1:5672 (1)',
                '127.0.0.1:40100 -> 127.0.0.1:5672',
                20,
                true,
                'up',
            ),
        ]);

        $lines = new QueueDetailRenderer()->render($queue, $detail, 100);

        $this->assertContains('Publish 27.3/s  Delivery 25.1/s  Durable yes  Auto-delete no  Exclusive no', $lines);
        $this->assertContains('1. worker]0;bad', $lines);
        $this->assertContains('   Channel 127.0.0.1:40100 -> 127.0.0.1:5672 (1)', $lines);
        $this->assertContains('   Connection 127.0.0.1:40100 -> 127.0.0.1:5672', $lines);
        $this->assertContains('   Prefetch 20  Ack yes  Status up', $lines);
    }

    public function testItUsesAStackedConsumerLayoutInANarrowTerminal(): void
    {
        $queue = new QueueSnapshot('/', 'jobs', 0, 0, 1, 0, 'classic', true, false, false, 'running');
        $detail = new QueueDetailSnapshot($queue, null, null, [
            new QueueConsumerSnapshot('worker', 'long-channel-name', 'long-connection-name', 10, false, null),
        ]);

        $lines = new QueueDetailRenderer()->render($queue, $detail, 20);

        $this->assertContains('1. worker', $lines);
        $this->assertContains('Channel: long-channe', $lines);
        $this->assertContains('Connection: long-con', $lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(20, AnsiUtils::visibleWidth($line));
        }
    }

    public function testItKeepsLargeCountersVisibleAtTheInlineBreakpoint(): void
    {
        $queue = new QueueSnapshot('/', 'jobs', 999999999999, 888888888888, 777777777777, 1888888888888, 'classic', true, false, false, 'running');

        $lines = new QueueDetailRenderer()->render($queue, null, 64, 20);

        $this->assertContains('Ready  999,999,999,999', $lines);
        $this->assertContains('Unacknowledged  888,888,888,888', $lines);
        $this->assertContains('Total  1,888,888,888,888', $lines);
        $this->assertContains('Consumers  777,777,777,777', $lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(64, AnsiUtils::visibleWidth($line));
        }
    }
}

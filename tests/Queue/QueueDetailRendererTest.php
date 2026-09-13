<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueDetailRenderer;
use App\Queue\QueueSnapshot;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;

final class QueueDetailRendererTest extends TestCase
{
    public function testItRendersStableQueueDetailsWithinTheAvailableWidth(): void
    {
        $lines = new QueueDetailRenderer()->render(new QueueSnapshot('/', "jobs\x1b]0;bad\x07", 12, 3, null, 15, 'quorum', true, false, true, 'running'), 30);

        $this->assertSame('Queue: jobs]0;bad', $lines[0]);
        $this->assertContains('Type            quorum', $lines);
        $this->assertContains('State           running', $lines);
        $this->assertContains('Consumers       -', $lines);
        $this->assertContains('Exclusive       yes', $lines);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(30, AnsiUtils::visibleWidth($line));
        }
    }
}

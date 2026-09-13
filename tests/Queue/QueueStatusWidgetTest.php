<?php

declare(strict_types=1);

namespace App\Tests\Queue;

use App\Queue\QueueStatusWidget;
use App\Queue\QueueViewState;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;

final class QueueStatusWidgetTest extends TestCase
{
    public function testItTruncatesARefreshFailureToTheAvailableWidth(): void
    {
        $state = new QueueViewState([]);
        $state->setRefreshFailure("Refresh failed: server returned \x1b[31m an unexpectedly long response.");

        $lines = new QueueStatusWidget($state)->render(new RenderContext(12, 1));

        $this->assertCount(1, $lines);
        $this->assertLessThanOrEqual(12, AnsiUtils::visibleWidth($lines[0]));
        $this->assertStringNotContainsString("\x1b", $lines[0]);
    }
}

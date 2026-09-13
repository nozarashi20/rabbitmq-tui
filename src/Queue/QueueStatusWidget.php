<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class QueueStatusWidget extends AbstractWidget
{
    public function __construct(private readonly QueueViewState $state)
    {
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $status = $this->state->status();
        if (null === $status) {
            return [];
        }

        $status = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($status));

        return [AnsiUtils::truncateToWidth($status, max(1, $context->getColumns()), '')];
    }
}

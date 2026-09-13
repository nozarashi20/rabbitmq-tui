<?php

declare(strict_types=1);

namespace App\QueueOverview;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;

final class QueueOverviewWidget extends AbstractWidget
{
    /**
     * @param list<Queue> $queues
     */
    public function __construct(
        private readonly array $queues,
        private readonly QueueOverviewRenderer $renderer,
    ) {
    }

    /**
     * @return list<string>
     */
    public function render(RenderContext $context): array
    {
        return $this->renderer->render($this->queues, $context->getColumns());
    }
}

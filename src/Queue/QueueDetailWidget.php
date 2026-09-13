<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

final class QueueDetailWidget extends AbstractWidget implements VerticallyExpandableInterface
{
    private bool $verticallyExpanded = false;

    public function __construct(
        private readonly QueueViewState $state,
        private readonly QueueDetailRenderer $renderer,
    ) {
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $queue = $this->state->selectedQueue();
        if (null === $queue) {
            return [];
        }

        return \array_slice($this->renderer->render($queue, $context->getColumns()), 0, max(1, $context->getRows()));
    }

    public function expandVertically(bool $expand): static
    {
        if ($this->verticallyExpanded !== $expand) {
            $this->verticallyExpanded = $expand;
            $this->invalidate();
        }

        return $this;
    }

    public function isVerticallyExpanded(): bool
    {
        return $this->verticallyExpanded;
    }
}

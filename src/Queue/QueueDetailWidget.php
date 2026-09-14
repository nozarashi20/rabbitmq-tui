<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Render\RenderContext;
use Symfony\Component\Tui\Widget\AbstractWidget;
use Symfony\Component\Tui\Widget\FocusableInterface;
use Symfony\Component\Tui\Widget\FocusableTrait;
use Symfony\Component\Tui\Widget\KeybindingsTrait;
use Symfony\Component\Tui\Widget\VerticallyExpandableInterface;

final class QueueDetailWidget extends AbstractWidget implements FocusableInterface, VerticallyExpandableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private bool $verticallyExpanded = false;
    private int $consumerOffset = 0;
    private int $visibleConsumerRows = 1;

    public function __construct(
        private readonly QueueViewState $state,
        private readonly QueueDetailRenderer $renderer,
    ) {
    }

    public function handleInput(string $data): void
    {
        $detail = $this->state->queueDetail();
        $maxOffset = null === $detail ? 0 : max(0, \count($detail->consumers) - 1);
        $keybindings = $this->getKeybindings();
        $offset = $this->consumerOffset;
        $pageSize = max(1, $this->visibleConsumerRows);

        if ($keybindings->matches($data, 'detail_up')) {
            $offset = max(0, $offset - 1);
        } elseif ($keybindings->matches($data, 'detail_down')) {
            $offset = min($maxOffset, $offset + 1);
        } elseif ($keybindings->matches($data, 'detail_page_up')) {
            $offset = max(0, $offset - $pageSize);
        } elseif ($keybindings->matches($data, 'detail_page_down')) {
            $offset = min($maxOffset, $offset + $pageSize);
        } else {
            return;
        }

        if ($offset !== $this->consumerOffset) {
            $this->consumerOffset = $offset;
            $this->invalidate();
        }
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        $queue = $this->state->selectedQueue();
        if (null === $queue) {
            return [];
        }

        $rows = max(1, $context->getRows());
        $lines = $this->renderer->render($queue, $this->state->queueDetail(), $context->getColumns(), $rows, $this->consumerOffset);
        $headingIndex = array_search('Consumers', $lines, true);
        if (false !== $headingIndex) {
            $this->visibleConsumerRows = max(1, $rows - $headingIndex - 1);
        }

        return \array_slice($lines, 0, $rows);
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

    /** @return array<string, string[]> */
    protected static function getDefaultKeybindings(): array
    {
        return [
            'detail_up' => [Key::UP],
            'detail_down' => [Key::DOWN],
            'detail_page_up' => [Key::PAGE_UP],
            'detail_page_down' => [Key::PAGE_DOWN],
        ];
    }
}

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

final class QueueOverviewWidget extends AbstractWidget implements FocusableInterface, VerticallyExpandableInterface
{
    use FocusableTrait;
    use KeybindingsTrait;

    private bool $verticallyExpanded = false;

    /** @param callable(): void $onInspect */
    public function __construct(
        private readonly QueueViewState $state,
        private readonly QueueOverviewRenderer $renderer,
        private readonly \Closure $onInspect,
        private readonly ?\Closure $onFocusFilter = null,
    ) {
    }

    public function handleInput(string $data): void
    {
        $keybindings = $this->getKeybindings();
        if ($keybindings->matches($data, 'queue_up')) {
            $this->state->moveUp();
            $this->invalidate();

            return;
        }

        if ($keybindings->matches($data, 'queue_down')) {
            $this->state->moveDown();
            $this->invalidate();

            return;
        }

        if ($keybindings->matches($data, 'queue_inspect')) {
            $this->state->openSelectedQueue();
            if ($this->state->showingDetail()) {
                ($this->onInspect)();
            }

            return;
        }

        if ($keybindings->matches($data, 'queue_filter')) {
            $this->onFocusFilter?->__invoke();
        }
    }

    /** @return list<string> */
    public function render(RenderContext $context): array
    {
        return $this->renderer->render(
            $this->state->filteredQueues(),
            $context->getColumns(),
            $this->state->selectedFilteredIndex(),
            max(1, $context->getRows()),
            '' === $this->state->filter() ? 'No queues found.' : 'No queues match filter.',
        );
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
        return ['queue_up' => [Key::UP], 'queue_down' => [Key::DOWN], 'queue_inspect' => [Key::ENTER], 'queue_filter' => ['/']];
    }
}

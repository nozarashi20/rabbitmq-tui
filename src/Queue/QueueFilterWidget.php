<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Event\ChangeEvent;
use Symfony\Component\Tui\Widget\InputWidget;

final class QueueFilterWidget extends InputWidget
{
    /** @param callable(): void $onFilterChanged @param callable(): void $onLeaveFilter */
    public function __construct(
        private readonly QueueViewState $state,
        private readonly \Closure $onFilterChanged,
        private readonly \Closure $onLeaveFilter,
    ) {
        parent::__construct();
        $this->setPrompt('Filter: ');
        $this->setValue($this->state->filter());
        $this->onChange(function (ChangeEvent $event): void {
            $this->state->setFilter($event->getValue());
            ($this->onFilterChanged)();
        });
        $this->onSubmit(function (): void {
            ($this->onLeaveFilter)();
        });
        $this->onCancel(function (): void {
            if ('' === $this->getValue()) {
                ($this->onLeaveFilter)();

                return;
            }

            $this->setValue('');
            $this->state->setFilter('');
            ($this->onFilterChanged)();
        });
    }
}

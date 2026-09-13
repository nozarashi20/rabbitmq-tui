<?php

declare(strict_types=1);

namespace App\Queue;

final class QueueViewState
{
    private int $selectedIndex = 0;
    private bool $showingDetail = false;

    /** @param list<QueueSnapshot> $queues */
    public function __construct(private readonly array $queues)
    {
    }

    public function selectedIndex(): int
    {
        return $this->selectedIndex;
    }

    /** @return list<QueueSnapshot> */
    public function queues(): array
    {
        return $this->queues;
    }

    public function selectedQueue(): ?QueueSnapshot
    {
        return $this->queues[$this->selectedIndex] ?? null;
    }

    public function showingDetail(): bool
    {
        return $this->showingDetail;
    }

    public function moveUp(): void
    {
        if ([] === $this->queues) {
            return;
        }

        $this->selectedIndex = 0 === $this->selectedIndex ? \count($this->queues) - 1 : $this->selectedIndex - 1;
    }

    public function moveDown(): void
    {
        if ([] === $this->queues) {
            return;
        }

        $this->selectedIndex = $this->selectedIndex === \count($this->queues) - 1 ? 0 : $this->selectedIndex + 1;
    }

    public function openSelectedQueue(): void
    {
        $this->showingDetail = null !== $this->selectedQueue();
    }

    public function returnToOverview(): void
    {
        $this->showingDetail = false;
    }
}

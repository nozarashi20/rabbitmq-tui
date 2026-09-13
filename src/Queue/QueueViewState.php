<?php

declare(strict_types=1);

namespace App\Queue;

final class QueueViewState
{
    private int $selectedIndex = 0;
    private bool $showingDetail = false;
    private ?string $refreshFailure = null;
    private ?string $notice = null;

    /** @param list<QueueSnapshot> $queues */
    public function __construct(private array $queues)
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

    /** @param list<QueueSnapshot> $queues */
    public function replaceQueues(array $queues): bool
    {
        $selectedQueue = $this->selectedQueue();
        $wasShowingDetail = $this->showingDetail;
        $this->queues = $queues;
        $this->notice = null;

        $selectedIndex = $this->indexOf($selectedQueue);
        if ([] === $queues) {
            $this->selectedIndex = 0;
        } elseif (null !== $selectedIndex) {
            $this->selectedIndex = $selectedIndex;
        } else {
            $this->selectedIndex = min($this->selectedIndex, \count($queues) - 1);
        }

        if ($wasShowingDetail && null === $this->indexOf($selectedQueue)) {
            $this->showingDetail = false;
            $this->notice = 'Inspected queue disappeared.';
        }

        return $wasShowingDetail && !$this->showingDetail;
    }

    public function setRefreshFailure(string $message): void
    {
        $this->refreshFailure = $message;
    }

    public function clearRefreshFailure(): void
    {
        $this->refreshFailure = null;
    }

    public function status(): ?string
    {
        return $this->refreshFailure ?? $this->notice;
    }

    private function indexOf(?QueueSnapshot $needle): ?int
    {
        if (null === $needle) {
            return null;
        }

        foreach ($this->queues as $index => $queue) {
            if ($queue->vhost === $needle->vhost && $queue->name === $needle->name) {
                return $index;
            }
        }

        return null;
    }
}

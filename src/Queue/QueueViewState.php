<?php

declare(strict_types=1);

namespace App\Queue;

use function Symfony\Component\String\u;

final class QueueViewState
{
    private int $selectedIndex = 0;
    private bool $showingDetail = false;
    private ?string $refreshFailure = null;
    private ?string $detailRefreshFailure = null;
    private ?string $notice = null;
    private string $filter = '';
    private ?QueueDetailSnapshot $queueDetail = null;

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

    public function filter(): string
    {
        return $this->filter;
    }

    /** @return list<QueueSnapshot> */
    public function filteredQueues(): array
    {
        if ('' === $this->filter) {
            return $this->queues;
        }

        return array_values(array_filter($this->queues, fn (QueueSnapshot $queue): bool => $this->matchesFilter($queue)));
    }

    public function selectedFilteredIndex(): ?int
    {
        $selectedQueue = $this->selectedQueue();
        if (null === $selectedQueue) {
            return null;
        }

        foreach ($this->filteredQueues() as $index => $queue) {
            if ($this->sameQueue($queue, $selectedQueue)) {
                return $index;
            }
        }

        return null;
    }

    public function setFilter(string $filter): void
    {
        if ($this->filter === $filter) {
            return;
        }

        $this->filter = $filter;
        $this->reconcileFilterSelection();
    }

    public function showingDetail(): bool
    {
        return $this->showingDetail;
    }

    public function queueDetail(): ?QueueDetailSnapshot
    {
        $queue = $this->selectedQueue();
        if (null === $queue || null === $this->queueDetail || !$this->sameQueue($queue, $this->queueDetail->queue)) {
            return null;
        }

        return $this->queueDetail;
    }

    public function moveUp(): void
    {
        $queues = $this->filteredQueues();
        if ([] === $queues) {
            return;
        }

        $selectedIndex = $this->selectedFilteredIndex() ?? 0;
        $this->selectQueue($queues[0 === $selectedIndex ? \count($queues) - 1 : $selectedIndex - 1]);
    }

    public function moveDown(): void
    {
        $queues = $this->filteredQueues();
        if ([] === $queues) {
            return;
        }

        $selectedIndex = $this->selectedFilteredIndex() ?? 0;
        $this->selectQueue($queues[$selectedIndex === \count($queues) - 1 ? 0 : $selectedIndex + 1]);
    }

    public function openSelectedQueue(): void
    {
        $this->showingDetail = null !== $this->selectedFilteredIndex();
        if (null === $this->queueDetail()) {
            $this->queueDetail = null;
            $this->detailRefreshFailure = null;
        }
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
            $this->queueDetail = null;
            $this->detailRefreshFailure = null;
            $this->notice = 'Inspected queue disappeared.';
        }

        $this->reconcileFilterSelection();

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

    public function replaceQueueDetail(QueueDetailSnapshot $detail): bool
    {
        $queue = $this->selectedQueue();
        if (!$this->showingDetail || null === $queue || !$this->sameQueue($queue, $detail->queue)) {
            return false;
        }

        $this->queueDetail = $detail;

        return true;
    }

    public function setDetailRefreshFailure(string $message): void
    {
        $this->detailRefreshFailure = $message;
    }

    public function clearDetailRefreshFailure(): void
    {
        $this->detailRefreshFailure = null;
    }

    public function status(): ?string
    {
        return ($this->showingDetail ? $this->detailRefreshFailure : null) ?? $this->refreshFailure ?? $this->notice;
    }

    private function indexOf(?QueueSnapshot $needle): ?int
    {
        if (null === $needle) {
            return null;
        }

        foreach ($this->queues as $index => $queue) {
            if ($this->sameQueue($queue, $needle)) {
                return $index;
            }
        }

        return null;
    }

    private function reconcileFilterSelection(): void
    {
        $matchingQueues = $this->filteredQueues();
        if ([] === $matchingQueues || null !== $this->selectedFilteredIndex()) {
            return;
        }

        $nearestIndex = null;
        $nearestDistance = null;
        foreach ($matchingQueues as $queue) {
            $index = $this->indexOf($queue);
            if (null === $index) {
                continue;
            }

            $distance = abs($index - $this->selectedIndex);
            if (null === $nearestDistance || $distance <= $nearestDistance) {
                $nearestIndex = $index;
                $nearestDistance = $distance;
            }
        }

        if (null !== $nearestIndex) {
            $this->selectedIndex = $nearestIndex;
        }
    }

    private function matchesFilter(QueueSnapshot $queue): bool
    {
        $filter = $this->lower($this->filter);

        return str_contains($this->lower($queue->name), $filter) || str_contains($this->lower($queue->vhost), $filter);
    }

    private function selectQueue(QueueSnapshot $queue): void
    {
        $index = $this->indexOf($queue);
        if (null !== $index) {
            $this->selectedIndex = $index;
        }
    }

    private function sameQueue(QueueSnapshot $left, QueueSnapshot $right): bool
    {
        return $left->vhost === $right->vhost && $left->name === $right->name;
    }

    private function lower(string $value): string
    {
        return u($value)->lower()->toString();
    }
}

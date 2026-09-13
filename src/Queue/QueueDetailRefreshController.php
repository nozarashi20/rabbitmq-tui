<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

final class QueueDetailRefreshController
{
    private ?QueueDetailRefreshInterface $refresh = null;
    private ?QueueSnapshot $target = null;
    private float $nextRefreshAt = \INF;

    public function __construct(
        private readonly QueueProviderInterface $queueProvider,
        private readonly QueueViewState $state,
    ) {
    }

    public function inspect(QueueSnapshot $queue, float $now): void
    {
        $this->refresh?->cancel();
        $this->refresh = null;
        $this->target = $queue;
        $this->nextRefreshAt = $now;
    }

    /** Returns true when visible detail refresh state changed. */
    public function tick(float $now): bool
    {
        $selectedQueue = $this->state->showingDetail() ? $this->state->selectedQueue() : null;
        if (null === $selectedQueue) {
            $this->stop();

            return false;
        }

        if (null === $this->target || !$this->sameQueue($this->target, $selectedQueue)) {
            $this->inspect($selectedQueue, $now);
        }

        if (null === $this->refresh && $now < $this->nextRefreshAt) {
            return false;
        }

        try {
            $this->refresh ??= $this->queueProvider->startDetailRefresh($selectedQueue);
            $detail = $this->refresh->advance();
        } catch (HttpClientException|\UnexpectedValueException $exception) {
            $this->refresh?->cancel();
            $this->refresh = null;

            if ($exception instanceof ClientExceptionInterface && 404 === $exception->getResponse()->getStatusCode()) {
                $this->state->replaceQueues(array_values(array_filter(
                    $this->state->queues(),
                    fn (QueueSnapshot $queue): bool => !$this->sameQueue($queue, $selectedQueue),
                )));
                $this->nextRefreshAt = \INF;

                return true;
            }

            $this->nextRefreshAt = $now + QueueRefreshController::INTERVAL_SECONDS;
            $this->state->setDetailRefreshFailure('Detail refresh failed: ' . $exception->getMessage());

            return true;
        }

        if (null === $detail) {
            return false;
        }

        $this->refresh = null;
        $this->nextRefreshAt = $now + QueueRefreshController::INTERVAL_SECONDS;
        $this->state->clearDetailRefreshFailure();

        return $this->state->replaceQueueDetail($detail);
    }

    public function isRefreshing(): bool
    {
        return null !== $this->refresh;
    }

    public function stop(): void
    {
        $this->refresh?->cancel();
        $this->refresh = null;
        $this->target = null;
        $this->nextRefreshAt = \INF;
    }

    private function sameQueue(QueueSnapshot $left, QueueSnapshot $right): bool
    {
        return $left->vhost === $right->vhost && $left->name === $right->name;
    }
}

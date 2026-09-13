<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

final class QueueRefreshController
{
    public const float INTERVAL_SECONDS = 2.0;

    private ?QueueRefreshInterface $refresh = null;
    private float $nextRefreshAt;

    public function __construct(
        private readonly QueueProviderInterface $queueProvider,
        private readonly QueueViewState $state,
        float $startedAt,
    ) {
        $this->nextRefreshAt = $startedAt + self::INTERVAL_SECONDS;
    }

    /** Returns true when visible refresh state changed. */
    public function tick(float $now): bool
    {
        if (null === $this->refresh && $now < $this->nextRefreshAt) {
            return false;
        }

        try {
            $this->refresh ??= $this->queueProvider->startRefresh();
            $queues = $this->refresh->advance();
        } catch (HttpClientException|\UnexpectedValueException $exception) {
            $this->refresh?->cancel();
            $this->refresh = null;
            $this->nextRefreshAt = $now + self::INTERVAL_SECONDS;
            $this->state->setRefreshFailure('Refresh failed: ' . $exception->getMessage());

            return true;
        }

        if (null === $queues) {
            return false;
        }

        $this->refresh = null;
        $this->nextRefreshAt = $now + self::INTERVAL_SECONDS;
        $this->state->clearRefreshFailure();
        $this->state->replaceQueues($queues);

        return true;
    }

    public function isRefreshing(): bool
    {
        return null !== $this->refresh;
    }

    public function stop(): void
    {
        $this->refresh?->cancel();
        $this->refresh = null;
    }
}

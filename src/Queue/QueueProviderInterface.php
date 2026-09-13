<?php

declare(strict_types=1);

namespace App\Queue;

interface QueueProviderInterface
{
    /**
     * @return list<QueueSnapshot>
     */
    public function queues(): array;

    public function startRefresh(): QueueRefreshInterface;

    public function startDetailRefresh(QueueSnapshot $queue): QueueDetailRefreshInterface;
}

<?php

declare(strict_types=1);

namespace App\Queue;

interface QueueRefreshInterface
{
    /**
     * @return list<QueueSnapshot>|null null while the complete snapshot is not ready
     */
    public function advance(?float $timeout = 0.0): ?array;

    public function cancel(): void;
}

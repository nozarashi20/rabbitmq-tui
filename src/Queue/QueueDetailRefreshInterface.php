<?php

declare(strict_types=1);

namespace App\Queue;

interface QueueDetailRefreshInterface
{
    /** Returns null while the snapshot is not ready. */
    public function advance(?float $timeout = 0.0): ?QueueDetailSnapshot;

    public function cancel(): void;
}

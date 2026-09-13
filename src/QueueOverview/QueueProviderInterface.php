<?php

declare(strict_types=1);

namespace App\QueueOverview;

interface QueueProviderInterface
{
    /**
     * @return list<Queue>
     */
    public function queues(): array;
}

<?php

declare(strict_types=1);

namespace App\Queue;

final readonly class QueueConsumerSnapshot
{
    public function __construct(
        public string $tag,
        public ?string $channelName,
        public ?string $connectionName,
        public int $prefetchCount,
        public bool $acknowledgementRequired,
        public ?string $activityStatus,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace App\Queue;

final readonly class QueueSnapshot
{
    public function __construct(
        public string $vhost,
        public string $name,
        public int $readyMessages,
        public int $unacknowledgedMessages,
        public ?int $consumers,
        public int $totalMessages,
        public string $type,
        public bool $durable,
        public bool $autoDelete,
        public bool $exclusive,
        public ?string $state,
    ) {
    }
}

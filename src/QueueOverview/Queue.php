<?php

declare(strict_types=1);

namespace App\QueueOverview;

final readonly class Queue
{
    public function __construct(
        public string $vhost,
        public string $name,
        public int $readyMessages,
        public int $unacknowledgedMessages,
        public ?int $consumers,
    ) {
    }
}

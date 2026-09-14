<?php

declare(strict_types=1);

namespace App\Queue;

final readonly class QueueDetailSnapshot
{
    /** @param list<QueueConsumerSnapshot> $consumers */
    public function __construct(
        public QueueSnapshot $queue,
        public ?float $publishRate,
        public ?float $deliveryRate,
        public array $consumers,
    ) {
    }
}

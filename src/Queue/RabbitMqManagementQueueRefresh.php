<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RabbitMqManagementQueueRefresh implements QueueRefreshInterface
{
    private int $page = 1;
    private ?ResponseInterface $response = null;

    /** @var list<QueueSnapshot> */
    private array $queues = [];

    /**
     * @param \Closure(array<mixed>, int): array{list<mixed>, bool} $items
     * @param \Closure(mixed): QueueSnapshot                        $mapQueue
     */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly \Closure $items,
        private readonly \Closure $mapQueue,
    ) {
    }

    public function advance(?float $timeout = 0.0): ?array
    {
        $this->response ??= $this->httpClient->request('GET', '/api/queues', [
            'query' => [
                'page' => $this->page,
                'page_size' => 100,
                'pagination' => 'true',
            ],
        ]);

        foreach ($this->httpClient->stream($this->response, $timeout) as $response => $chunk) {
            if ($chunk->isTimeout()) {
                return null;
            }

            if (!$chunk->isLast()) {
                continue;
            }

            [$items, $hasMorePages] = ($this->items)($response->toArray(), $this->page);
            foreach ($items as $item) {
                $this->queues[] = ($this->mapQueue)($item);
            }

            $this->response = null;
            if ($hasMorePages) {
                ++$this->page;

                return null;
            }

            usort($this->queues, static fn (QueueSnapshot $left, QueueSnapshot $right): int => ($left->vhost <=> $right->vhost) ?: ($left->name <=> $right->name));

            return $this->queues;
        }

        return null;
    }

    public function cancel(): void
    {
        $this->response?->cancel();
        $this->response = null;
    }
}

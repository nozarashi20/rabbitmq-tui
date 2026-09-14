<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

final class RabbitMqManagementQueueDetailRefresh implements QueueDetailRefreshInterface
{
    private ?ResponseInterface $response = null;

    /** @param \Closure(array<mixed>): QueueDetailSnapshot $mapDetail */
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $vhost,
        private readonly string $name,
        private readonly \Closure $mapDetail,
    ) {
    }

    public function advance(?float $timeout = 0.0): ?QueueDetailSnapshot
    {
        $this->response ??= $this->httpClient->request('GET', \sprintf(
            '/api/queues/%s/%s',
            rawurlencode($this->vhost),
            rawurlencode($this->name),
        ));

        foreach ($this->httpClient->stream($this->response, $timeout) as $response => $chunk) {
            if ($chunk->isTimeout()) {
                return null;
            }

            if (!$chunk->isLast()) {
                continue;
            }

            $detail = ($this->mapDetail)($response->toArray());
            if ($detail->queue->vhost !== $this->vhost || $detail->queue->name !== $this->name) {
                throw new \UnexpectedValueException('RabbitMQ returned details for a different queue.');
            }

            $this->response = null;

            return $detail;
        }

        return null;
    }

    public function cancel(): void
    {
        $this->response?->cancel();
        $this->response = null;
    }
}

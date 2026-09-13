<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use Symfony\Component\DependencyInjection\Attribute\Target;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[AsAlias(QueueProviderInterface::class)]
final readonly class RabbitMqManagementQueueProvider implements QueueProviderInterface
{
    private const int PAGE_SIZE = 100;

    public function __construct(
        #[Target('rabbitmq.management')]
        private HttpClientInterface $httpClient,
    ) {
    }

    /**
     * @return list<QueueSnapshot>
     *
     * @throws HttpClientException
     * @throws \UnexpectedValueException
     */
    public function queues(): array
    {
        $queues = [];

        for ($page = 1;; ++$page) {
            $response = $this->httpClient->request('GET', '/api/queues', [
                'query' => [
                    'page' => $page,
                    'page_size' => self::PAGE_SIZE,
                    'pagination' => 'true',
                ],
            ]);

            [$items, $hasMorePages] = $this->items($response->toArray(), $page);
            foreach ($items as $item) {
                $queues[] = $this->mapQueue($item);
            }

            if (!$hasMorePages) {
                break;
            }
        }

        usort($queues, static fn (QueueSnapshot $left, QueueSnapshot $right): int => ($left->vhost <=> $right->vhost) ?: ($left->name <=> $right->name));

        return $queues;
    }

    /**
     * @param array<mixed> $response
     *
     * @return array{list<mixed>, bool}
     */
    private function items(array $response, int $page): array
    {
        if (array_is_list($response)) {
            return [$response, false];
        }

        if (!isset($response['items']) || !\is_array($response['items'])) {
            throw new \UnexpectedValueException('RabbitMQ returned a queue list with no items field.');
        }

        $pageCount = $response['page_count'] ?? 1;
        if (!\is_int($pageCount) || $pageCount < 0 || (0 !== $pageCount && $pageCount < $page)) {
            throw new \UnexpectedValueException('RabbitMQ returned an invalid queue page count.');
        }

        return [array_values($response['items']), $page < $pageCount];
    }

    private function mapQueue(mixed $item): QueueSnapshot
    {
        if (!\is_array($item) || !isset($item['vhost']) || !\is_string($item['vhost']) || !isset($item['name']) || !\is_string($item['name'])) {
            throw new \UnexpectedValueException('RabbitMQ returned a queue without a valid virtual host and name.');
        }

        return new QueueSnapshot(
            $item['vhost'],
            $item['name'],
            $this->requiredCount($item, 'messages_ready'),
            $this->requiredCount($item, 'messages_unacknowledged'),
            $this->optionalCount($item, 'consumers'),
            $this->requiredCount($item, 'messages'),
            $this->queueType($item),
            $this->requiredBoolean($item, 'durable'),
            $this->requiredBoolean($item, 'auto_delete'),
            $this->requiredBoolean($item, 'exclusive'),
            $this->optionalString($item, 'state'),
        );
    }

    /**
     * @param array<mixed> $item
     */
    private function requiredCount(array $item, string $field): int
    {
        if (!isset($item[$field]) || !\is_int($item[$field]) || $item[$field] < 0) {
            throw new \UnexpectedValueException(\sprintf('RabbitMQ returned an invalid %s value.', $field));
        }

        return $item[$field];
    }

    /**
     * @param array<mixed> $item
     */
    private function optionalCount(array $item, string $field): ?int
    {
        if (!\array_key_exists($field, $item) || null === $item[$field]) {
            return null;
        }

        if (!\is_int($item[$field]) || $item[$field] < 0) {
            throw new \UnexpectedValueException(\sprintf('RabbitMQ returned an invalid %s value.', $field));
        }

        return $item[$field];
    }

    /**
     * @param array<mixed> $item
     */
    private function queueType(array $item): string
    {
        if (isset($item['type']) && \is_string($item['type']) && '' !== $item['type']) {
            return $item['type'];
        }

        if (isset($item['arguments']) && \is_array($item['arguments']) && isset($item['arguments']['x-queue-type']) && \is_string($item['arguments']['x-queue-type']) && '' !== $item['arguments']['x-queue-type']) {
            return $item['arguments']['x-queue-type'];
        }

        return 'classic';
    }

    /**
     * @param array<mixed> $item
     */
    private function requiredBoolean(array $item, string $field): bool
    {
        if (!isset($item[$field]) || !\is_bool($item[$field])) {
            throw new \UnexpectedValueException(\sprintf('RabbitMQ returned an invalid %s value.', $field));
        }

        return $item[$field];
    }

    /**
     * @param array<mixed> $item
     */
    private function optionalString(array $item, string $field): ?string
    {
        if (!\array_key_exists($field, $item) || null === $item[$field]) {
            return null;
        }

        if (!\is_string($item[$field]) || '' === $item[$field]) {
            throw new \UnexpectedValueException(\sprintf('RabbitMQ returned an invalid %s value.', $field));
        }

        return $item[$field];
    }
}

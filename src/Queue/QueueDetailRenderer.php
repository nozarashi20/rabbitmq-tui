<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class QueueDetailRenderer
{
    private const int INLINE_MIN_COLUMNS = 64;
    private const int COMPACT_MAX_ROWS = 15;

    /** @return list<string> */
    public function render(QueueSnapshot $queue, ?QueueDetailSnapshot $detail, int $columns, ?int $rows = null, int $consumerOffset = 0): array
    {
        $queue = $detail?->queue ?? $queue;
        $width = max(1, $columns);
        if (null !== $rows && $rows <= self::COMPACT_MAX_ROWS) {
            return $this->compactRender($queue, $detail, $width, $consumerOffset);
        }

        $lines = [$this->line('Queue: ' . $this->text($queue->name), $width)];

        if ($width >= self::INLINE_MIN_COLUMNS) {
            $lines[] = $this->line(\sprintf(
                'Vhost %s  Type %s  State %s',
                $this->text($queue->vhost),
                $this->text($queue->type),
                $this->text($queue->state ?? '-'),
            ), $width);
            $counterLine = \sprintf(
                'Ready %s  Unacknowledged %s  Total %s  Consumers %s',
                number_format($queue->readyMessages),
                number_format($queue->unacknowledgedMessages),
                number_format($queue->totalMessages),
                null === $queue->consumers ? '-' : number_format($queue->consumers),
            );
            if (AnsiUtils::visibleWidth($counterLine) <= $width) {
                $lines[] = $this->line($counterLine, $width);
            } else {
                foreach ([
                    'Ready' => number_format($queue->readyMessages),
                    'Unacknowledged' => number_format($queue->unacknowledgedMessages),
                    'Total' => number_format($queue->totalMessages),
                    'Consumers' => null === $queue->consumers ? '-' : number_format($queue->consumers),
                ] as $label => $value) {
                    $lines[] = $this->line($label . '  ' . $value, $width);
                }
            }
            $lines[] = $this->line(\sprintf(
                'Publish %s  Delivery %s  Durable %s  Auto-delete %s  Exclusive %s',
                $this->rate($detail?->publishRate),
                $this->rate($detail?->deliveryRate),
                $this->yesNo($queue->durable),
                $this->yesNo($queue->autoDelete),
                $this->yesNo($queue->exclusive),
            ), $width);
        } else {
            $fields = [
                'Virtual host' => $queue->vhost,
                'Type' => $queue->type,
                'State' => $queue->state ?? '-',
                'Ready' => number_format($queue->readyMessages),
                'Unacknowledged' => number_format($queue->unacknowledgedMessages),
                'Total' => number_format($queue->totalMessages),
                'Consumers' => null === $queue->consumers ? '-' : number_format($queue->consumers),
                'Publish rate' => $this->rate($detail?->publishRate),
                'Delivery rate' => $this->rate($detail?->deliveryRate),
                'Durable' => $this->yesNo($queue->durable),
                'Auto-delete' => $this->yesNo($queue->autoDelete),
                'Exclusive' => $this->yesNo($queue->exclusive),
            ];
            $labelWidth = min(16, max(array_map(strlen(...), array_keys($fields))));
            foreach ($fields as $label => $value) {
                $lines[] = $this->line(str_pad($label, $labelWidth) . '  ' . $this->text($value), $width);
            }
        }

        $lines[] = '';
        $lines[] = $this->line('Consumers', $width);
        if (null === $detail) {
            $lines[] = $this->line('Loading consumer data…', $width);

            return $lines;
        }

        if ([] === $detail->consumers) {
            $lines[] = $this->line('No consumers attached.', $width);

            return $lines;
        }

        foreach (\array_slice($detail->consumers, $consumerOffset, null, true) as $index => $consumer) {
            $lines = [...$lines, ...$this->consumerLines($consumer, $index, $width)];
        }

        return $lines;
    }

    /** @return list<string> */
    private function compactRender(QueueSnapshot $queue, ?QueueDetailSnapshot $detail, int $width, int $consumerOffset): array
    {
        $lines = [
            $this->line('Queue: ' . $this->text($queue->name), $width),
            $this->line($this->text($queue->vhost) . ' · ' . $this->text($queue->state ?? '-'), $width),
            $this->line(\sprintf(
                'R %s  U %s  T %s  C %s',
                number_format($queue->readyMessages),
                number_format($queue->unacknowledgedMessages),
                number_format($queue->totalMessages),
                null === $queue->consumers ? '-' : number_format($queue->consumers),
            ), $width),
            $this->line(\sprintf('Pub %s  Del %s', $this->rate($detail?->publishRate), $this->rate($detail?->deliveryRate)), $width),
            $this->line('Consumers', $width),
        ];

        if (null === $detail) {
            $lines[] = $this->line('Loading…', $width);

            return $lines;
        }

        if ([] === $detail->consumers) {
            $lines[] = $this->line('None attached.', $width);

            return $lines;
        }

        foreach (\array_slice($detail->consumers, $consumerOffset, null, true) as $index => $consumer) {
            $lines[] = $this->line(\sprintf('%d. %s', $index + 1, $this->text($consumer->tag)), $width);
            $lines[] = $this->line('Ch ' . $this->text($consumer->channelName ?? $consumer->connectionName ?? '-'), $width);
            $lines[] = $this->line(\sprintf(
                'Prefetch %d  Ack %s  %s',
                $consumer->prefetchCount,
                $this->yesNo($consumer->acknowledgementRequired),
                $this->text($consumer->activityStatus ?? '-'),
            ), $width);
        }

        return $lines;
    }

    /** @return list<string> */
    private function consumerLines(QueueConsumerSnapshot $consumer, int $index, int $width): array
    {
        $tag = $this->text($consumer->tag);
        $channel = $this->text($consumer->channelName ?? '-');
        $connection = $this->text($consumer->connectionName ?? '-');
        $status = $this->text($consumer->activityStatus ?? '-');
        $ack = $this->yesNo($consumer->acknowledgementRequired);

        if ($width >= self::INLINE_MIN_COLUMNS) {
            return [
                $this->line(\sprintf('%d. %s', $index + 1, $tag), $width),
                $this->line('   Channel ' . $channel, $width),
                $this->line('   Connection ' . $connection, $width),
                $this->line(\sprintf('   Prefetch %d  Ack %s  Status %s', $consumer->prefetchCount, $ack, $status), $width),
            ];
        }

        return [
            $this->line(\sprintf('%d. %s', $index + 1, $tag), $width),
            $this->line('Channel: ' . $channel, $width),
            $this->line('Connection: ' . $connection, $width),
            $this->line(\sprintf('Prefetch: %d  Ack: %s  Status: %s', $consumer->prefetchCount, $ack, $status), $width),
        ];
    }

    private function rate(?float $rate): string
    {
        return null === $rate ? '-' : number_format($rate, 1) . '/s';
    }

    private function yesNo(bool $value): string
    {
        return $value ? 'yes' : 'no';
    }

    private function text(string $value): string
    {
        $value = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));

        return '' === $value ? '(unnamed)' : $value;
    }

    private function line(string $value, int $width): string
    {
        return AnsiUtils::truncateToWidth($value, $width, '');
    }
}

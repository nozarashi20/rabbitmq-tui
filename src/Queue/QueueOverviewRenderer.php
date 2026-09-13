<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class QueueOverviewRenderer
{
    private const int COMPACT_LAYOUT_MIN_COLUMNS = 19;
    private const int OVERVIEW_LAYOUT_MIN_COLUMNS = 32;
    private const int VHOST_LAYOUT_MIN_COLUMNS = 48;
    private const int VHOST_WIDTH = 10;
    private const array COMPACT_METRIC_COLUMNS = [
        'Ready' => 5,
        'Unack' => 6,
    ];
    private const array OVERVIEW_METRIC_COLUMNS = [
        ...self::COMPACT_METRIC_COLUMNS,
        'Consumers' => 9,
    ];

    /**
     * @param list<QueueSnapshot> $queues
     *
     * @return list<string>
     */
    public function render(array $queues, int $columns, ?int $selectedIndex = null, ?int $maxVisible = null, string $emptyMessage = 'No queues found.'): array
    {
        $columns = max(1, $columns);

        if ([] === $queues) {
            return [AnsiUtils::truncateToWidth($emptyMessage, $columns, '')];
        }

        if ($columns < self::COMPACT_LAYOUT_MIN_COLUMNS) {
            if (null === $selectedIndex) {
                return array_map(fn (QueueSnapshot $queue): string => $this->queueLabel($queue, $columns), $queues);
            }

            return $this->visibleLines(array_map(fn (QueueSnapshot $queue): string => $this->queueLabel($queue, $columns), $queues), $columns, $selectedIndex, $maxVisible);
        }

        if ($columns < self::OVERVIEW_LAYOUT_MIN_COLUMNS) {
            return $this->selectableTable($queues, $columns, self::COMPACT_METRIC_COLUMNS, selectedIndex: $selectedIndex, maxVisible: $maxVisible);
        }

        if ($columns < self::VHOST_LAYOUT_MIN_COLUMNS) {
            return $this->selectableTable($queues, $columns, self::OVERVIEW_METRIC_COLUMNS, rightPadding: 1, selectedIndex: $selectedIndex, maxVisible: $maxVisible);
        }

        return $this->selectableTable(
            $queues,
            $columns,
            self::OVERVIEW_METRIC_COLUMNS,
            vhostWidth: self::VHOST_WIDTH,
            rightPadding: 1,
            selectedIndex: $selectedIndex,
            maxVisible: $maxVisible,
        );
    }

    /** @param list<QueueSnapshot> $queues @param array<string, int> $columns @return list<string> */
    private function selectableTable(array $queues, int $availableColumns, array $columns, ?int $vhostWidth = null, int $rightPadding = 0, ?int $selectedIndex = null, ?int $maxVisible = null): array
    {
        if (null === $selectedIndex) {
            return $this->renderTable($queues, $availableColumns, $columns, $vhostWidth, $rightPadding);
        }

        $lines = $this->renderTable($queues, max(1, $availableColumns - 2), $columns, $vhostWidth, $rightPadding);

        if (null !== $maxVisible && $maxVisible <= 1) {
            return $this->visibleLines(\array_slice($lines, 1), $availableColumns, $selectedIndex, 1);
        }

        $header = '  ' . $lines[0];
        $rows = $this->visibleLines(\array_slice($lines, 1), $availableColumns, $selectedIndex, null === $maxVisible ? null : max(1, $maxVisible - 1));

        return [$header, ...$rows];
    }

    /** @param list<string> $lines @return list<string> */
    private function visibleLines(array $lines, int $columns, ?int $selectedIndex, ?int $maxVisible): array
    {
        if (null === $selectedIndex) {
            return $lines;
        }

        $selectedIndex = max(0, min($selectedIndex, \count($lines) - 1));
        $visible = max(1, min($maxVisible ?? \count($lines), \count($lines)));
        $start = max(0, min($selectedIndex - intdiv($visible, 2), \count($lines) - $visible));
        $result = [];
        foreach (\array_slice($lines, $start, $visible, true) as $index => $line) {
            $prefix = $index === $selectedIndex ? '> ' : '  ';
            $line = AnsiUtils::truncateToWidth($prefix . $line, $columns, '');
            $result[] = $index === $selectedIndex ? new Style(reverse: true)->apply($line) : $line;
        }

        return $result;
    }

    /**
     * @param list<QueueSnapshot> $queues
     * @param array<string, int>  $columns
     *
     * @return list<string>
     */
    private function renderTable(array $queues, int $availableColumns, array $columns, ?int $vhostWidth = null, int $rightPadding = 0): array
    {
        $nameWidth = $availableColumns - array_sum($columns) - \count($columns) - $rightPadding;
        if (null !== $vhostWidth) {
            $nameWidth -= 1 + $vhostWidth;
        }

        $lines = [$this->padRight('Name', $nameWidth)];
        if (null !== $vhostWidth) {
            $lines[0] .= ' ' . $this->padRight('Vhost', $vhostWidth);
        }
        foreach ($columns as $label => $width) {
            $lines[0] .= ' ' . $this->padLeft($label, $width);
        }

        foreach ($queues as $queue) {
            $line = $this->padRight(
                null === $vhostWidth ? $this->queueLabel($queue, $nameWidth) : $this->queueName($queue->name, $nameWidth),
                $nameWidth,
            );
            if (null !== $vhostWidth) {
                $line .= ' ' . $this->padRight($this->vhost($queue->vhost, $vhostWidth), $vhostWidth);
            }
            foreach ($columns as $label => $width) {
                $line .= ' ' . $this->padLeft($this->metric($queue, $label, $width), $width);
            }
            $lines[] = $line;
        }

        return $lines;
    }

    private function metric(QueueSnapshot $queue, string $label, int $width): string
    {
        $value = match ($label) {
            'Ready' => $queue->readyMessages,
            'Unack' => $queue->unacknowledgedMessages,
            'Consumers' => $queue->consumers,
        };

        if (null === $value) {
            return '-';
        }

        $formatted = number_format($value);
        if (AnsiUtils::visibleWidth($formatted) <= $width) {
            return $formatted;
        }

        return AnsiUtils::truncateToWidth((string) $value, $width, '…');
    }

    private function queueLabel(QueueSnapshot $queue, int $width): string
    {
        return AnsiUtils::truncateToWidth($this->vhost($queue->vhost, $width) . ' · ' . $this->queueName($queue->name, $width), $width, '');
    }

    private function queueName(string $name, int $width): string
    {
        return $this->value($name, $width);
    }

    private function vhost(string $vhost, int $width): string
    {
        return $this->value($vhost, $width);
    }

    private function value(string $value, int $width): string
    {
        $value = StringUtils::stripControlBytes(StringUtils::sanitizeUtf8($value));

        return AnsiUtils::truncateToWidth('' === $value ? '(unnamed)' : $value, $width, '');
    }

    private function padLeft(string $value, int $width): string
    {
        return str_repeat(' ', max(0, $width - AnsiUtils::visibleWidth($value))) . $value;
    }

    private function padRight(string $value, int $width): string
    {
        return $value . str_repeat(' ', max(0, $width - AnsiUtils::visibleWidth($value)));
    }
}

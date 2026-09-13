<?php

declare(strict_types=1);

namespace App\Queue;

use Symfony\Component\Tui\Ansi\AnsiUtils;
use Symfony\Component\Tui\Widget\Util\StringUtils;

final class QueueDetailRenderer
{
    /** @return list<string> */
    public function render(QueueSnapshot $queue, int $columns): array
    {
        $fields = [
            'Virtual host' => $queue->vhost,
            'Type' => $queue->type,
            'State' => $queue->state ?? '-',
            'Ready' => number_format($queue->readyMessages),
            'Unacknowledged' => number_format($queue->unacknowledgedMessages),
            'Total' => number_format($queue->totalMessages),
            'Consumers' => null === $queue->consumers ? '-' : number_format($queue->consumers),
            'Durable' => $this->yesNo($queue->durable),
            'Auto-delete' => $this->yesNo($queue->autoDelete),
            'Exclusive' => $this->yesNo($queue->exclusive),
        ];
        $width = max(1, $columns);
        $labelWidth = min(16, max(array_map(strlen(...), array_keys($fields))));
        $lines = [AnsiUtils::truncateToWidth('Queue: ' . $this->text($queue->name), $width, '')];

        foreach ($fields as $label => $value) {
            $line = str_pad($label, $labelWidth) . '  ' . $this->text($value);
            $lines[] = AnsiUtils::truncateToWidth($line, $width, '');
        }

        return $lines;
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
}

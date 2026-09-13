<?php

declare(strict_types=1);

namespace App\Command;

use App\Queue\QueueDetailRenderer;
use App\Queue\QueueDetailWidget;
use App\Queue\QueueOverviewRenderer;
use App\Queue\QueueOverviewWidget;
use App\Queue\QueueProviderInterface;
use App\Queue\QueueViewState;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Tui\Event\InputEvent;
use Symfony\Component\Tui\Input\Key;
use Symfony\Component\Tui\Input\Keybindings;
use Symfony\Component\Tui\Style\Style;
use Symfony\Component\Tui\Tui;
use Symfony\Component\Tui\Widget\TextWidget;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface as HttpClientException;

#[AsCommand(
    name: 'rabbitmq:tui',
    description: 'Start the RabbitMQ terminal interface.',
)]
final readonly class RabbitMqTuiCommand
{
    public function __construct(
        private QueueProviderInterface $queueProvider,
        private QueueOverviewRenderer $renderer,
        private QueueDetailRenderer $detailRenderer,
    ) {
    }

    public function __invoke(InputInterface $input, SymfonyStyle $io): int
    {
        if (!$input->isInteractive()) {
            $io->error('The RabbitMQ terminal interface requires an interactive terminal.');

            return Command::FAILURE;
        }

        try {
            $queues = $this->queueProvider->queues();
        } catch (HttpClientException|\UnexpectedValueException $exception) {
            $io->error(\sprintf('Cannot load RabbitMQ queues: %s', $exception->getMessage()));

            return Command::FAILURE;
        }

        $state = new QueueViewState($queues);
        $keybindings = new Keybindings([
            'quit' => ['q', Key::shift('q'), Key::ctrl('c')],
            'back' => [Key::ESCAPE],
        ]);
        $tui = new Tui(keybindings: $keybindings);
        $showOverview = function () use ($tui, $state): void {
            $tui->clear();
            $tui->add(new TextWidget('RabbitMQ queues')->setStyle(new Style(bold: true, color: 'cyan')));
            $widget = new QueueOverviewWidget($state, $this->renderer, function () use ($tui, $state): void {
                $queue = $state->selectedQueue();
                if (null === $queue) {
                    return;
                }

                $tui->clear();
                $tui->add(new TextWidget('Queue details')->setStyle(new Style(bold: true, color: 'cyan')));
                $tui->add(new QueueDetailWidget($queue, $this->detailRenderer)->expandVertically(true));
                $tui->add(new TextWidget('Esc Back   q or Ctrl-C Exit')->setStyle(new Style(color: 'gray')));
            })->expandVertically(true);
            $tui->add($widget);
            $tui->add(new TextWidget('Up/Down Navigate   Enter Inspect   q or Ctrl-C Exit')->setStyle(new Style(color: 'gray')));
            $tui->setFocus($widget);
        };
        $showOverview();
        $tui->addListener(static function (InputEvent $event) use ($keybindings, $tui, $state, $showOverview): void {
            if ($keybindings->matches($event->getData(), 'quit')) {
                $event->stopPropagation();
                $tui->stop();

                return;
            }

            if (!$state->showingDetail() || !$keybindings->matches($event->getData(), 'back')) {
                return;
            }

            $event->stopPropagation();
            $state->returnToOverview();
            $showOverview();
        });
        $tui->run();

        return Command::SUCCESS;
    }
}

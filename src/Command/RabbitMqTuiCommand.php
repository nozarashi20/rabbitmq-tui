<?php

declare(strict_types=1);

namespace App\Command;

use App\QueueOverview\QueueOverviewRenderer;
use App\QueueOverview\QueueOverviewWidget;
use App\QueueOverview\QueueProviderInterface;
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

        $keybindings = new Keybindings([
            'quit' => ['q', Key::shift('q'), Key::ctrl('c')],
        ]);
        $tui = new Tui(keybindings: $keybindings);
        $tui->add((new TextWidget('RabbitMQ queues'))->setStyle(new Style(bold: true, color: 'cyan')));
        $tui->add(new QueueOverviewWidget($queues, $this->renderer));
        $tui->add((new TextWidget('q or Ctrl-C to exit'))->setStyle(new Style(color: 'gray')));
        $tui->addListener(static function (InputEvent $event) use ($keybindings, $tui): void {
            if (!$keybindings->matches($event->getData(), 'quit')) {
                return;
            }

            $event->stopPropagation();
            $tui->stop();
        });
        $tui->run();

        return Command::SUCCESS;
    }
}

<?php

declare(strict_types=1);

namespace App\Command;

use App\Queue\QueueDetailRenderer;
use App\Queue\QueueDetailWidget;
use App\Queue\QueueFilterWidget;
use App\Queue\QueueOverviewRenderer;
use App\Queue\QueueOverviewWidget;
use App\Queue\QueueProviderInterface;
use App\Queue\QueueRefreshController;
use App\Queue\QueueStatusWidget;
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
        $refreshController = new QueueRefreshController($this->queueProvider, $state, microtime(true));
        $keybindings = new Keybindings([
            'quit' => ['q', Key::shift('q')],
            'force_quit' => [Key::ctrl('c')],
            'back' => [Key::ESCAPE],
        ]);
        $tui = new Tui(keybindings: $keybindings);
        $overviewWidget = null;
        $filterWidget = null;
        $detailWidget = null;
        $statusWidget = null;
        $showDetail = function () use ($tui, $state, &$detailWidget, &$statusWidget): void {
            if (null === $state->selectedQueue()) {
                return;
            }

            $tui->clear();
            $tui->add(new TextWidget('Queue details')->setStyle(new Style(bold: true, color: 'cyan')));
            $detailWidget = new QueueDetailWidget($state, $this->detailRenderer)->expandVertically(true);
            $statusWidget = new QueueStatusWidget($state);
            $tui->add($detailWidget);
            $tui->add($statusWidget);
            $tui->add(new TextWidget('Esc Back   q or Ctrl-C Exit', true)->setStyle(new Style(color: 'gray')));
        };
        $showOverview = function () use ($tui, $state, $showDetail, &$overviewWidget, &$filterWidget, &$statusWidget): void {
            $tui->clear();
            $tui->add(new TextWidget('RabbitMQ queues')->setStyle(new Style(bold: true, color: 'cyan')));
            $filterWidget = new QueueFilterWidget(
                $state,
                static function () use (&$overviewWidget): void {
                    $overviewWidget?->invalidate();
                },
                static function () use ($tui, &$overviewWidget): void {
                    $tui->setFocus($overviewWidget);
                },
            );
            $overviewWidget = new QueueOverviewWidget(
                $state,
                $this->renderer,
                $showDetail,
                static function () use ($tui, &$filterWidget): void {
                    $tui->setFocus($filterWidget);
                },
            )->expandVertically(true);
            $statusWidget = new QueueStatusWidget($state);
            $tui->add($filterWidget);
            $tui->add($overviewWidget);
            $tui->add($statusWidget);
            $tui->add(new TextWidget('/ Filter   Up/Down Navigate   Enter Inspect   q or Ctrl-C Exit', true)->setStyle(new Style(color: 'gray')));
            $tui->setFocus($overviewWidget);
        };
        $showOverview();
        $tui->addListener(static function (InputEvent $event) use ($keybindings, $tui, $state, $showOverview, $refreshController, &$filterWidget): void {
            if ($keybindings->matches($event->getData(), 'force_quit') || (!$filterWidget?->isFocused() && $keybindings->matches($event->getData(), 'quit'))) {
                $event->stopPropagation();
                $refreshController->stop();
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
        $tui->onTick(static function () use ($state, $refreshController, $showOverview, &$overviewWidget, &$detailWidget, &$statusWidget): ?bool {
            $wasShowingDetail = $state->showingDetail();
            if (!$refreshController->tick(microtime(true))) {
                return $refreshController->isRefreshing() ?: null;
            }

            if ($wasShowingDetail && !$state->showingDetail()) {
                $showOverview();

                return $refreshController->isRefreshing() ?: null;
            }

            if ($state->showingDetail()) {
                $detailWidget?->invalidate();
            } else {
                $overviewWidget?->invalidate();
            }
            $statusWidget?->invalidate();

            return $refreshController->isRefreshing() ?: null;
        });
        $tui->run();

        return Command::SUCCESS;
    }
}

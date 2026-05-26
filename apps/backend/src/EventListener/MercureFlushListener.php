<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Service\MercurePublisherService;
use Symfony\Component\Console\Event\ConsoleTerminateEvent;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class MercureFlushListener
{
    public function __construct(
        private readonly MercurePublisherService $mercurePublisher
    ) {
    }

    #[AsEventListener(event: KernelEvents::TERMINATE)]
    public function onKernelTerminate(TerminateEvent $event): void
    {
        $this->mercurePublisher->flush();
    }

    #[AsEventListener(event: 'console.terminate')]
    public function onConsoleTerminate(ConsoleTerminateEvent $event): void
    {
        $this->mercurePublisher->flush();
    }
}

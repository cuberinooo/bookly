<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class LocaleListener implements EventSubscriberInterface
{
    private string $defaultLocale;

    public function __construct(string $defaultLocale = 'en')
    {
        $this->defaultLocale = $defaultLocale;
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        $request = $event->getRequest();

        if (!$request->headers->has('Accept-Language')) {
            return;
        }

        $locale = $request->headers->get('Accept-Language');
        
        // The frontend sends the locale directly (e.g., 'en' or 'de')
        // We only want to support these two for now.
        // We also strip potential sub-tags if they were to be added (e.g. 'en-US' -> 'en')
        $baseLocale = substr($locale, 0, 2);

        if (in_array($baseLocale, ['en', 'de'])) {
            $request->setLocale($baseLocale);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            // Must be registered before the default LocaleListener
            KernelEvents::REQUEST => [['onKernelRequest', 20]],
        ];
    }
}

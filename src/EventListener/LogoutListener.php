<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Security\Http\Event\LogoutEvent;

#[AsEventListener(event: LogoutEvent::class)]
class LogoutListener
{
    public function __invoke(LogoutEvent $event): void
    {
        $request = $event->getRequest();

        $referer = $request->headers->get('referer');

        if (
            $referer &&
            !str_contains($referer, '/logout')
        ) {
            $event->setResponse(
                new RedirectResponse($referer)
            );
        }
    }
}
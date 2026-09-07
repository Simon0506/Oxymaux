<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Twig\Environment;

#[AsEventListener(event: 'kernel.request', priority: 100)]
final class MaintenanceListener
{
    public function __construct(
        private readonly bool $maintenanceMode,
        private readonly Environment $twig,
    ) {}

    public function __invoke(RequestEvent $event): void
    {
        if (!$this->maintenanceMode || !$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $path = $request->getPathInfo();

        if (str_starts_with($path, '/build/') || str_starts_with($path, '/images/') || $path === '/favicon.ico') {
            return;
        }

        $event->setResponse(new Response(
            $this->twig->render('maintenance.html.twig'),
            Response::HTTP_SERVICE_UNAVAILABLE,
            [
                'Retry-After' => '3600',
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]
        ));
    }
}

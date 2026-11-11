<?php

namespace App\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Rejects the legacy EasyAdmin 2/3 URL format (?crudAction=…&crudControllerFqcn=…)
 * before EasyAdmin's own subscribers can process it and throw a PHP 500 error.
 *
 * EasyAdmin 4 uses path-based routing (/admin/{crudAction}/{entityId}). The old
 * query-param form is unsupported and must return 404 so clients get a clear signal
 * rather than a confusing 500.
 *
 * Priority 100 ensures this runs before EasyAdmin's AdminRouteSubscriber.
 */
class LegacyAdminUrlSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 100],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();

        if (
            $request->query->has('crudAction')
            || $request->query->has('crudControllerFqcn')
        ) {
            throw new NotFoundHttpException('Legacy EasyAdmin URL format is not supported.');
        }
    }
}

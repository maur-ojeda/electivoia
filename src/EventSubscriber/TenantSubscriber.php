<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\TenantContext;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TenantContext $tenantContext,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 10 so it runs after firewall (priority 8) but before controllers
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return;
        }

        $school = $user->getSchool();
        if ($school !== null) {
            $this->tenantContext->setCurrentSchool($school);
        }
    }
}

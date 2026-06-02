<?php

namespace App\EventSubscriber;

use App\Entity\User;
use App\Service\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class TenantSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private TenantContext $tenantContext,
        private EntityManagerInterface $entityManager,
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            // Priority 7 so it runs AFTER firewall (priority 8) but before controllers.
            // The firewall must authenticate first so $token->getUser() returns the User entity.
            KernelEvents::REQUEST => ['onKernelRequest', 7],
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

        // Refresh the user from DB so all relations (including school) are loaded.
        // The user from the security token is a serialized snapshot; lazy relations
        // may be null or uninitialized proxies after deserialization.
        $this->entityManager->refresh($user);

        $school = $user->getSchool();
        if ($school !== null) {
            $this->tenantContext->setCurrentSchool($school);
        }
    }
}

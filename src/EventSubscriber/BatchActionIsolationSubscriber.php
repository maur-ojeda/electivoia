<?php

namespace App\EventSubscriber;

use App\Security\SameSchoolVoter;
use Doctrine\Persistence\ManagerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Event\BeforeCrudActionEvent;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Enforces the school boundary and the entity class on EasyAdmin batch actions.
 *
 * EasyAdmin's own batch-delete guard cannot do this: EntityDto::newWithInstance()
 * rebuilds the DTO with `new self(...)`, which resets isAccessible back to true,
 * so the per-entity permission check never fires. The action permission for
 * DELETE is also null by default, so EA_EXECUTE_ACTION passes.
 *
 * Two things are validated before any entity is touched:
 *  1. The posted entityFqcn must match the entity of the current CRUD controller,
 *     because EasyAdmin resolves the target repository from the request body.
 *  2. Every targeted entity must pass the SAME_SCHOOL voter.
 */
#[AsEventListener(event: BeforeCrudActionEvent::class)]
class BatchActionIsolationSubscriber
{
    public function __construct(
        private readonly ManagerRegistry $doctrine,
        private readonly Security $security,
    ) {
    }

    public function __invoke(BeforeCrudActionEvent $event): void
    {
        $context = $event->getAdminContext();
        $request = $context->getRequest();

        $entityIds = $request->request->all()[EA::BATCH_ACTION_ENTITY_IDS] ?? null;
        if (!\is_array($entityIds) || [] === $entityIds) {
            return;
        }

        $entityDto = $context->getEntity();
        $entityFqcn = $entityDto->getFqcn();

        if ($entityFqcn !== $request->request->get(EA::ENTITY_FQCN)) {
            throw new AccessDeniedHttpException('The batch action entity class does not match the current CRUD controller.');
        }

        $entityManager = $this->doctrine->getManagerForClass($entityFqcn);
        $repository = $entityManager->getRepository($entityFqcn);

        foreach ($entityIds as $entityId) {
            $entity = $repository->find($entityId);
            if (null === $entity) {
                continue;
            }

            if (!$this->security->isGranted(SameSchoolVoter::ATTRIBUTE, $entity)) {
                throw new AccessDeniedHttpException(sprintf('Cross-school batch action refused for %s#%s.', $entityFqcn, $entityId));
            }
        }
    }
}

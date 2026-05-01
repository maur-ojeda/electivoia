<?php

namespace App\Security\Voter;

use App\Entity\Course;
use App\Entity\Enrollment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Prevents cross-school access to Course and Enrollment resources.
 * Sprint 6: Enforce school boundary when a user's school is set.
 */
class SchoolVoter extends Voter
{
    const VIEW = 'school_view';
    const EDIT = 'school_edit';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT], true)
            && ($subject instanceof Course || $subject instanceof Enrollment);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // Super admins bypass school boundaries
        if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }

        $userSchool = $user->getSchool();

        // No school assigned to user → single-tenant / legacy mode, allow
        if ($userSchool === null) {
            return true;
        }

        $resourceSchool = match (true) {
            $subject instanceof Course     => $subject->getSchool(),
            $subject instanceof Enrollment => $subject->getCourse()->getSchool(),
            default                        => null,
        };

        // Resource not yet assigned to a school → allow
        if ($resourceSchool === null) {
            return true;
        }

        return $userSchool->getId() === $resourceSchool->getId();
    }
}

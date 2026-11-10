<?php

namespace App\Security;

use App\Entity\School;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Grants access only when the entity belongs to the same school as the
 * authenticated user.  ROLE_SUPER_ADMIN and school-less users always pass.
 */
class SameSchoolVoter extends Voter
{
    public const ATTRIBUTE = 'SAME_SCHOOL';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === self::ATTRIBUTE && $subject !== null;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Super admins bypass all school checks
        if (in_array('ROLE_SUPER_ADMIN', $token->getRoleNames(), true)) {
            return true;
        }

        $userSchool = $user->getSchool();
        if ($userSchool === null) {
            return true; // no school on user → global actor
        }

        $entitySchool = $this->resolveSchool($subject);
        if ($entitySchool === null) {
            return true; // entity has no school → tenant-agnostic
        }

        return $entitySchool->getId() === $userSchool->getId();
    }

    private function resolveSchool(mixed $entity): ?School
    {
        if (method_exists($entity, 'getSchool')) {
            return $entity->getSchool();
        }
        // Enrollment → Course → School
        if (method_exists($entity, 'getCourse') && $entity->getCourse() !== null) {
            return $entity->getCourse()->getSchool();
        }
        // InterestProfile → Student(User) → School
        if (method_exists($entity, 'getStudent') && $entity->getStudent() !== null) {
            return $entity->getStudent()->getSchool();
        }
        return null;
    }
}

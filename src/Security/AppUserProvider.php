<?php

namespace App\Security;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * Loads users by RUT, normalizing the identifier before lookup:
 * strips dots and uppercases the check digit K.
 * Example: "30.100.001-k" → "30100001-K"
 */
class AppUserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $normalized = self::normalizeRut($identifier);

        $user = $this->em->getRepository(User::class)->findOneBy(['rut' => $normalized]);

        if ($user === null) {
            throw new UserNotFoundException(sprintf('RUT "%s" not found.', $normalized));
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        return $this->loadUserByIdentifier($user->getUserIdentifier());
    }

    public function supportsClass(string $class): bool
    {
        return $class === User::class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            return;
        }
        $user->setPassword($newHashedPassword);
        $this->em->flush();
    }

    public static function normalizeRut(string $rut): string
    {
        // Strip dots, uppercase K
        return strtoupper(str_replace('.', '', trim($rut)));
    }
}

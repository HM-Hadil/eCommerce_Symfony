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
use Psr\Log\LoggerInterface;

class UserProvider implements UserProviderInterface, PasswordUpgraderInterface
{
    private $entityManager;
    private $logger;

    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    ) {
        $this->entityManager = $entityManager;
        $this->logger = $logger;
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $identifier]);

        if (!$user) {
            $this->logger->warning('User not found', ['email' => $identifier]);
            $ex = new UserNotFoundException();
            $ex->setUserIdentifier($identifier);
            throw $ex;
        }

        if (!$user->isIsVerified()) {
            $this->logger->warning('User not verified', ['email' => $identifier]);
            throw new \Exception('Votre compte n\'est pas vérifié. Veuillez vérifier votre téléphone.');
        }

        if (!$user->isIsActive()) {
            $this->logger->warning('User account disabled', ['email' => $identifier]);
            throw new \Exception('Votre compte est désactivé. Veuillez contacter l\'administrateur.');
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', get_class($user)));
        }

        $refreshedUser = $this->entityManager->getRepository(User::class)->find($user->getId());
        
        if (!$refreshedUser) {
            throw new UserNotFoundException('User not found');
        }

        return $refreshedUser;
    }

    public function supportsClass(string $class): bool
    {
        return User::class === $class || is_subclass_of($class, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $user->setPassword($newHashedPassword);
        $this->entityManager->persist($user);
        $this->entityManager->flush();
    }
}
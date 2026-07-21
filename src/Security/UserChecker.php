<?php

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        if (!$user->isActive()) { // Vérifie si getter s'appelle isActive() ou isIsActive()
            throw new CustomUserMessageAuthenticationException('Votre compte n\'est pas encore activé. Veuillez vérifier vos emails.');
        }
    }

    // La signature correcte incluant TokenInterface
    public function checkPostAuth(UserInterface $user, TokenInterface $token = null): void
    {
        // Pas de vérification nécessaire ici
    }
}

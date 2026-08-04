<?php

namespace App\Security;

use App\Entity\User as AppUser;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(private RequestStack $requestStack) {}

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof AppUser) {
            return;
        }

        $request = $this->requestStack->getCurrentRequest();

        // SKIP CHECK ON INVITE PAGE
        if ($request && $request->attributes->get('_route') === 'app_invite_accept') {
            return;
        }

        if (!$user->isActive()) {
            throw new CustomUserMessageAuthenticationException('Votre compte n\'est pas encore activé. Veuillez vérifier vos emails.');
        }
    }

    public function checkPostAuth(UserInterface $user, TokenInterface $token = null): void
    {
        // rien
    }
}

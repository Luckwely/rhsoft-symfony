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
            // Cas 1 : le compte n'a jamais été vérifié (inscription/invitation en attente)
            if ($user->getEmailVerifiedAt() === null) {
                throw new CustomUserMessageAuthenticationException('Votre compte n\'est pas encore activé. Veuillez vérifier vos emails.');
            }

            // Cas 2 : le compte était vérifié, mais l'entreprise a été suspendue/désactivée
            $entreprise = $user->getEntreprise();
            if ($entreprise && !in_array($entreprise->getStatus(), ['active', 'trial'], true)) {
                throw new CustomUserMessageAuthenticationException('Votre entreprise a été suspendue. Merci de contacter votre administrateur ou notre support.');
            }

            // Cas 3 : le compte a été désactivé individuellement par un administrateur
            throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé. Merci de contacter votre administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user, TokenInterface $token = null): void
    {
        // rien
    }
}

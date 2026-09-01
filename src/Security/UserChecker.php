<?php

namespace App\Security;

use App\Entity\User as AppUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private RequestStack $requestStack,
        private EntityManagerInterface $entityManager
    ) {}

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

        // Once the approved notice period has ended, deactivate the account before
        // allowing authentication to continue.
        $dateSortie = $user->getDateSortie();
        if ($user->isActive() && $dateSortie && $dateSortie <= new \DateTimeImmutable('now')) {
            $user->setIsActive(false);
            $this->entityManager->flush();
        }

        // A suspended/inactive company blocks every linked user, including active accounts.
        $entreprise = $user->getEntreprise();
        if ($entreprise && !in_array($entreprise->getStatus(), ['active', 'trial'], true)) {
            throw new CustomUserMessageAuthenticationException('Votre entreprise est suspendue ou inactive. Merci de contacter votre administrateur ou notre support.');
        }

        if (!$user->isActive()) {
            // The account has not been verified yet.
            if ($user->getEmailVerifiedAt() === null) {
                throw new CustomUserMessageAuthenticationException('Votre compte n\'est pas encore activé. Veuillez vérifier vos emails.');
            }

            // The account itself has been disabled by an administrator.
            throw new CustomUserMessageAuthenticationException('Votre compte a été désactivé. Merci de contacter votre administrateur.');
        }
    }

    public function checkPostAuth(UserInterface $user, TokenInterface $token = null): void
    {
        // rien
    }
}

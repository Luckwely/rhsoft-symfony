<?php

namespace App\Security\Voter;

use App\Entity\Conge;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Allows RH or Manager users to edit (validate/refuse) a Conge request,
 * but only if that request belongs to their own entreprise (multi-tenant safety).
 */
final class RhEditVoter extends Voter
{
    protected function supports(string $attribute, mixed $subject): bool
    {
        return $attribute === 'RH_EDIT' && $subject instanceof Conge;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        // ROLE_ADMIN, ROLE_RH and ROLE_MANAGER are the roles allowed to edit leave requests;
        // the controllers already restrict access to these roles via #[IsGranted], this voter
        // only adds the cross-tenant safety check.
        /** @var Conge $conge */
        $conge = $subject;

        return $conge->getEntreprise() !== null
            && $user->getEntreprise() !== null
            && $conge->getEntreprise() === $user->getEntreprise();
    }
}

<?php

namespace App\Twig;

use App\Entity\User;
use App\Service\CongeManagerService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Expose le solde de congés réel (calculé dynamiquement à raison de 2,5 jours acquis
 * par mois d'ancienneté, moins les congés déjà validés) aux templates qui affichaient
 * jusqu'ici le champ statique User::soldeConge — lequel ne bouge jamais tout seul et
 * ne reflétait donc pas l'augmentation mensuelle du solde.
 */
class CongeExtension extends AbstractExtension
{
    public function __construct(
        private readonly CongeManagerService $congeManager,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('conge_solde', [$this, 'soldeReel']),
        ];
    }

    public function soldeReel(?User $user): float
    {
        if (!$user) {
            return 0.0;
        }

        return $this->congeManager->getLeaveBalanceData($user)['soldeConges'];
    }
}

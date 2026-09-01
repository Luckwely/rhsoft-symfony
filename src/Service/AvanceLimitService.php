<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\AvanceSalaireRepository;

/**
 * Centralise le calcul du plafond d'avance sur salaire autorisé pour un employé
 * (pourcentage du salaire de base, configurable par entreprise) et de son encours
 * actuel (somme des avances non encore remboursées ni refusées).
 *
 * Remplace l'ancienne limite fixe (50-800) qui était identique pour tout le monde
 * quel que soit le salaire ou le rôle.
 */
class AvanceLimitService
{
    private const PLAFOND_POURCENTAGE_DEFAUT = 50;
    private const MONTANT_MIN = 50.0;

    public function __construct(private AvanceSalaireRepository $avanceSalaireRepository)
    {
    }

    /**
     * Pourcentage du salaire de base autorisé en avance, tel que configuré sur l'entreprise
     * de l'employé (ou une valeur par défaut si non configurée).
     */
    public function getPlafondPourcentage(User $employee): int
    {
        return $employee->getEntreprise()?->getPlafondAvancePourcentage() ?? self::PLAFOND_POURCENTAGE_DEFAUT;
    }

    /**
     * Plafond total (en montant) qu'un employé ne peut pas dépasser en cumul d'avances
     * non remboursées, calculé à partir de son salaire de base.
     */
    public function getPlafondMontant(User $employee): float
    {
        $entreprise = $employee->getEntreprise();
        $salaireBase = $employee->getSalaireBase();

        // Existing employees may not have a personal salary yet. Fall back to the
        // salary configured for their role by the company instead of making the
        // available advance amount equal to zero.
        if ($salaireBase === null && $entreprise) {
            $salaireBase = $entreprise->getSalaireBaseForRoles($employee->getRoles());
        }

        return round(($salaireBase ?? 0.0) * $this->getPlafondPourcentage($employee) / 100, 2);
    }

    /**
     * Somme des avances actuellement "en cours" pour cet employé, c'est-à-dire ni
     * remboursées ni refusées (statuts demande, valide, payee) : c'est de l'argent
     * déjà engagé ou versé et pas encore récupéré sur salaire.
     */
    public function getEncours(User $employee): float
    {
        return $this->avanceSalaireRepository->sumEncoursByEmployee($employee);
    }

    /**
     * Montant restant disponible avant d'atteindre le plafond, compte tenu de l'encours actuel.
     */
    public function getMontantDisponible(User $employee): float
    {
        return max(0.0, $this->getPlafondMontant($employee) - $this->getEncours($employee));
    }

    /**
     * Vrai si l'encours actuel de l'employé (y compris une éventuelle nouvelle demande déjà
     * persistée) dépasse le plafond autorisé. Utilisé pour un contrôle serveur à l'approbation,
     * en plus du contrôle fait côté formulaire à la soumission.
     */
    public function depassePlafond(User $employee): bool
    {
        return $this->getEncours($employee) > $this->getPlafondMontant($employee) + 0.01; // tolérance d'arrondi
    }

    public function getMontantMinimum(): float
    {
        return self::MONTANT_MIN;
    }
}

<?php

namespace App\Service;

use App\Entity\Entreprise;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Centralizes all subscription/plan enforcement:
 * - Trial (essai) access expires 31 days after signup (checked against dateFinAbonnement).
 * - Paid plans (premium, vip) don't expire on a timer here (billing status flips to
 *   'active' on payment - see BillingController), but do cap employee headcount.
 *
 * Employee limits by plan:
 *   - essai   : no headcount limit (time-limited instead, see isAccessAllowed())
 *   - premium : 5 employees
 *   - vip     : 20 employees
 *   - anything else / null : falls back to the premium limit (safe default)
 */
class SubscriptionLimitService
{
    public const EMPLOYEE_LIMITS = [
        'essai' => null, // null = no headcount cap, access is time-limited instead
        'premium' => 5,
        'vip' => 20,
    ];

    private const DEFAULT_LIMIT = 5;

    public function __construct(
        private UserRepository $userRepository,
        private EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * Whether this entreprise is currently allowed to access the app at all
     * (used at login and on every authenticated request).
     *
     * If a trial has expired, this also flips the status to 'expired' and
     * persists it, so the SuperAdmin dashboard/reporting reflect reality
     * instead of showing a stale 'trial' status forever.
     */
    public function isAccessAllowed(?Entreprise $entreprise): bool
    {
        if (!$entreprise) {
            return false;
        }

        $status = $entreprise->getStatus();

        if ($status === 'active') {
            return true;
        }

        if ($status === 'trial') {
            $finAbonnement = $entreprise->getDateFinAbonnement();

            if ($finAbonnement !== null && $finAbonnement < new \DateTime()) {
                // Trial just expired: mark it so and persist, then deny access.
                $entreprise->setStatus('expired');
                $this->entityManager->flush();

                return false;
            }

            return true;
        }

        // status is 'pending', 'expired', 'suspended', etc.
        return false;
    }

    /**
     * A short, user-facing reason why access was denied, for flash messages.
     */
    public function getAccessDeniedReason(?Entreprise $entreprise): string
    {
        if (!$entreprise) {
            return "Aucune entreprise n'est associée à votre compte.";
        }

        return match ($entreprise->getStatus()) {
            'expired' => 'Votre période d\'essai de 31 jours est terminée. Merci de souscrire à un abonnement pour continuer.',
            'pending' => 'Votre abonnement est en attente de paiement.',
            default => 'Votre entreprise n\'a pas (ou plus) accès à la plateforme. Statut : ' . $entreprise->getStatus(),
        };
    }

    /**
     * The maximum number of employees this entreprise's plan allows.
     * Returns null if there is no headcount limit (e.g. active trial).
     */
    public function getEmployeeLimit(Entreprise $entreprise): ?int
    {
        $plan = $entreprise->getPlan();

        if ($plan !== null && array_key_exists($plan, self::EMPLOYEE_LIMITS)) {
            return self::EMPLOYEE_LIMITS[$plan];
        }

        return self::DEFAULT_LIMIT;
    }

    /**
     * Whether this entreprise can add one more employee right now.
     */
    public function canAddEmployee(Entreprise $entreprise): bool
    {
        $limit = $this->getEmployeeLimit($entreprise);

        if ($limit === null) {
            return true; // no cap for this plan (e.g. active trial)
        }

        return $this->userRepository->countByEntreprise($entreprise) < $limit;
    }

    /**
     * User-facing message for when the employee limit has been reached.
     */
    public function getEmployeeLimitReachedMessage(Entreprise $entreprise): string
    {
        $limit = $this->getEmployeeLimit($entreprise);
        $planName = match ($entreprise->getPlan()) {
            'premium' => 'Premium',
            'vip' => 'VIP',
            default => (string) $entreprise->getPlan(),
        };

        return sprintf(
            'Limite atteinte : votre plan %s autorise au maximum %d employé(s). Passez à un plan supérieur pour en ajouter davantage.',
            $planName,
            $limit
        );
    }
}

<?php

namespace App\Service;

use App\Entity\Entreprise;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 *
 *
 *
 *
 *
 *
 *
 *
 *
 *
 */
class SubscriptionLimitService
{
    public const EMPLOYEE_LIMITS = [
        'essai' => null,
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
     *
     *
     *
     *
     *
     *
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

                $entreprise->setStatus('expired');
                $this->entityManager->flush();

                return false;
            }

            return true;
        }


        return false;
    }

    /**
     *
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
     *
     *
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
     *
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
     * 
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

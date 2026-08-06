<?php

namespace App\Service;

use App\Entity\Conge;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CongeManagerService
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Calculates total days using DateTimeImmutable.
     */
    public function calculateDuration(\DateTimeImmutable $startDate, \DateTimeImmutable $endDate): float
    {
        $interval = $startDate->diff($endDate);
        return (float) ($interval->days + 1);
    }

    /**
     * Handles business rules, automatic assignments, and persistence.
     */
    public function processLeaveRequest(Conge $conge, User $user): void
    {
        // Automatically link the employee submitting the request
        $conge->setEmployee($user);

        // Automatically assign the company from the user relationship if available
        if (method_exists($user, 'getEntreprise') && $user->getEntreprise()) {
            $conge->setEntreprise($user->getEntreprise());
        }

        // Calculate and set number of days if dates are provided
        if ($conge->getDateDebut() && $conge->getDateFin()) {
            $nbJours = $this->calculateDuration($conge->getDateDebut(), $conge->getDateFin());
            $conge->setNbJours($nbJours);
        }

        // Enforce default status using entity constant
        $conge->setStatut(Conge::STATUS_DEMANDE);

        $this->entityManager->persist($conge);
        $this->entityManager->flush();
    }

    /**
     * Fetch user history.
     */
    public function getEmployeeHistory(User $user): array
    {
        return $this->entityManager->getRepository(Conge::class)->findBy(
            ['employee' => $user],
            ['createdAt' => 'DESC']
        );
    }

    /**
     * Placeholder for leave balance analytics.
     */
    public function getLeaveBalanceData(User $user): array
    {
        return [
            'soldeConges' => 18,
            'joursUtilises' => 7,
            'totalAnnee' => 30
        ];
    }
}

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

        $today = new \DateTimeImmutable('today');
        if ($conge->getDateDebut() && $conge->getDateDebut() < $today) {
            throw new \Exception("La date de début ne peut pas être dans le passé.");
        }

        if (method_exists($user, 'isEnPeriodeEssai') && $user->isEnPeriodeEssai()) {
            throw new \Exception("Impossible de poser des congés pendant la période d'essai.");
        }

        if ($conge->getDateDebut() && $conge->getDateFin()) {
            $nbJours = $this->calculateDuration($conge->getDateDebut(), $conge->getDateFin());
            $conge->setNbJours($nbJours);
        }

        $balanceData = $this->getLeaveBalanceData($user);
        if ($conge->getNbJours() > $balanceData['soldeConges']) {
            throw new \Exception("Solde insuffisant. Vous demandez " . $conge->getNbJours() . " jours mais il vous reste " . $balanceData['soldeConges'] . " jours.");
        }

        $conge->setEmployee($user);
        if (method_exists($user, 'getEntreprise') && $user->getEntreprise()) {
            $conge->setEntreprise($user->getEntreprise());
        }

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
     * Calcule le solde de congés réel de l'employé sur l'année en cours,
     * à partir de son quota annuel (User::soldeConge) et des congés validés déjà pris.
     */
    public function getLeaveBalanceData(User $user): array
    {
        $totalAnnee = $user->getSoldeConge() ?? 25.0;

        $anneeCourante = (int) (new \DateTimeImmutable())->format('Y');
        $debutAnnee = new \DateTimeImmutable("$anneeCourante-01-01 00:00:00");
        $finAnnee = new \DateTimeImmutable("$anneeCourante-12-31 23:59:59");

        $qb = $this->entityManager->getRepository(Conge::class)->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.nbJours), 0)')
            ->where('c.employee = :employee')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.dateDebut BETWEEN :debut AND :fin')
            ->setParameter('employee', $user)
            ->setParameter('statut', Conge::STATUS_VALIDE)
            ->setParameter('debut', $debutAnnee)
            ->setParameter('fin', $finAnnee);

        $joursUtilises = (float) $qb->getQuery()->getSingleScalarResult();

        return [
            'soldeConges' => max(0, $totalAnnee - $joursUtilises),
            'joursUtilises' => $joursUtilises,
            'totalAnnee' => $totalAnnee,
        ];
    }
}

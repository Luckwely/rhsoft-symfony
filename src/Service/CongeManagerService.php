<?php

namespace App\Service;

use App\Entity\Conge;
use App\Entity\Entreprise;
use App\Entity\Planning;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class CongeManagerService
{
    /** Nombre de jours acquis par mois travaillé (règle légale : 2,5 j/mois). */
    private const ACCRUAL_PER_MONTH = 2.5;

    /** Ancienneté minimale (en mois) requise avant de pouvoir demander un congé. */
    private const MIN_MONTHS_BEFORE_REQUEST = 3;

    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    /**
     * Nombre de mois complets travaillés depuis la date d'embauche.
     * Retourne 0 si la date d'embauche est inconnue.
     */
    public function getMonthsOfService(User $user): int
    {
        $dateEmbauche = $user->getDateEmbauche();
        if (!$dateEmbauche) {
            return 0;
        }

        $embauche = \DateTimeImmutable::createFromInterface($dateEmbauche);
        $now = new \DateTimeImmutable();

        if ($embauche > $now) {
            return 0;
        }

        $interval = $embauche->diff($now);

        return ($interval->y * 12) + $interval->m;
    }

    /**
     * Un employé ne peut poser de congé qu'à partir de 3 mois d'ancienneté.
     */
    public function canRequestLeave(User $user): bool
    {
        return $this->getMonthsOfService($user) >= self::MIN_MONTHS_BEFORE_REQUEST;
    }

    /**
     * Solde théorique acquis depuis l'embauche, à raison de 2,5 jours par mois travaillé.
     */
    public function getAccruedBalance(User $user): float
    {
        return $this->getMonthsOfService($user) * self::ACCRUAL_PER_MONTH;
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
     * Validate the leave business rules without persisting the request.
     *
     * $checkStartNotInPast is true at submission time (an employee cannot request
     * leave that starts before today) but must be false when re-validating an
     * *already submitted* request at approval time: an approver may legitimately
     * process a request a day or two after it was sent, by which point its start
     * date may have already arrived or passed. Re-applying the submission-time
     * check there would permanently block a validly-submitted request from ever
     * being approved (it could only be rejected), forcing the employee to resubmit
     * for no legitimate business reason.
     *
     * @throws \RuntimeException when seniority, dates or balance are invalid.
     */
    public function validateLeaveRequest(Conge $conge, User $user, bool $checkStartNotInPast = true): void
    {
        if (!$this->canRequestLeave($user)) {
            throw new \RuntimeException(sprintf(
                'Vous devez avoir au moins %d mois d\'ancienneté pour pouvoir demander un congé.',
                self::MIN_MONTHS_BEFORE_REQUEST
            ));
        }

        $start = $conge->getDateDebut();
        $end = $conge->getDateFin();
        if (!$start || !$end) {
            throw new \RuntimeException('Veuillez renseigner les dates de début et de fin du congé.');
        }

        $today = new \DateTimeImmutable('today');
        if ($checkStartNotInPast && $start < $today) {
            throw new \RuntimeException('La date de début du congé ne peut pas être antérieure à aujourd\'hui.');
        }
        if ($end < $start) {
            throw new \RuntimeException('La date de fin doit être postérieure ou égale à la date de début.');
        }

        $nbJours = $this->calculateDuration($start, $end);
        $soldeDisponible = $this->getLeaveBalanceData($user)['soldeConges'];
        if ($nbJours > $soldeDisponible + 0.0001) {
            throw new \RuntimeException(sprintf(
                'Solde de congés insuffisant : vous demandez %s jour(s) mais il ne vous reste que %s jour(s).',
                $nbJours,
                $soldeDisponible
            ));
        }
    }

    /**
     * Re-validates an already-submitted request at approval time: seniority and
     * balance are re-checked (they can genuinely change between submission and
     * approval), but the "start date not in the past" rule is intentionally
     * skipped — see validateLeaveRequest() for why.
     *
     * @throws \RuntimeException when seniority, dates or balance are invalid.
     */
    public function validateLeaveRequestForApproval(Conge $conge, User $user): void
    {
        $this->validateLeaveRequest($conge, $user, checkStartNotInPast: false);
    }

    /**
     * Handles business rules, automatic assignments, and persistence.
     */
    public function processLeaveRequest(Conge $conge, User $user): void
    {
        $this->validateLeaveRequest($conge, $user);

        $conge->setEmployee($user);
        if ($user->getEntreprise()) {
            $conge->setEntreprise($user->getEntreprise());
        }
        $conge->setNbJours($this->calculateDuration($conge->getDateDebut(), $conge->getDateFin()));
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
     * Calcule le solde de congés réel de l'employé, à partir de son acquisition
     * mensuelle (2,5 jours / mois d'ancienneté depuis la date d'embauche) et des
     * congés validés déjà pris depuis son embauche.
     *
     * Si la date d'embauche n'est pas renseignée, le solde calculé est nul et la demande
     * reste bloquée jusqu'à ce que la date d'embauche soit renseignée.
     */
    public function getLeaveBalanceData(User $user): array
    {
        // Sans date d’embauche, aucun solde acquis ne peut être calculé de manière fiable.
        $totalAnnee = $user->getDateEmbauche()
            ? $this->getAccruedBalance($user)
            : 0.0;

        $debutPeriode = $user->getDateEmbauche()
            ? \DateTimeImmutable::createFromInterface($user->getDateEmbauche())
            : new \DateTimeImmutable('1970-01-01 00:00:00');

        $qb = $this->entityManager->getRepository(Conge::class)->createQueryBuilder('c')
            ->select('COALESCE(SUM(c.nbJours), 0)')
            ->where('c.employee = :employee')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.dateDebut >= :debut')
            ->setParameter('employee', $user)
            ->setParameter('statut', Conge::STATUS_VALIDE)
            ->setParameter('debut', $debutPeriode);

        $joursUtilises = (float) $qb->getQuery()->getSingleScalarResult();

        return [
            'soldeConges' => max(0, $totalAnnee - $joursUtilises),
            'joursUtilises' => $joursUtilises,
            'totalAnnee' => $totalAnnee,
        ];
    }

    /**
     * Répercute automatiquement un congé validé sur le planning de l'employé :
     * chaque jour de la période devient un jour "congé" (verrouillé) dans le planning,
     * qu'il y ait déjà eu une entrée de planning ce jour-là ou non.
     */
    public function syncPlanningForValidatedConge(Conge $conge): void
    {
        $user = $conge->getEmployee();
        $entreprise = $conge->getEntreprise();
        $debut = $conge->getDateDebut();
        $fin = $conge->getDateFin();

        if (!$user || !$entreprise || !$debut || !$fin) {
            return;
        }

        $planningRepo = $this->entityManager->getRepository(Planning::class);
        $cursor = $debut;

        while ($cursor <= $fin) {
            $weekStart = $this->getWeekStartFor($cursor);
            $dayName = $this->getFrenchDayName($cursor);

            $planning = $planningRepo->findOneBy([
                'user' => $user,
                'weekStart' => $weekStart,
                'dayOfWeek' => $dayName,
            ]);

            if (!$planning) {
                $planning = new Planning();
                $planning->setUser($user);
                $planning->setEntreprise($entreprise);
                $planning->setWeekStart($weekStart);
                $planning->setDayOfWeek($dayName);
            }

            // Le congé validé prend systématiquement le dessus sur ce qui était planifié ce jour-là.
            $planning->setTypeJour(Planning::TYPE_CONGE);
            $planning->setHeureDebut(null);
            $planning->setHeureFin(null);
            $planning->setStatus(Planning::STATUT_VALIDE);
            $planning->setComment(sprintf(
                'Congé validé : %s',
                $conge->getTypeConge()?->getNom() ?? 'congé'
            ));
            $planning->setValidatedBy($conge->getValidePar());
            $planning->setValidatedAt(new \DateTimeImmutable());

            $this->entityManager->persist($planning);

            $cursor = $cursor->modify('+1 day');
        }

        $this->entityManager->flush();
    }

    /**
     * Construit un index [jour ('Y-m-d') => liste des employés en congé validé ce jour-là]
     * pour une entreprise et une période données. Sert à afficher un calendrier d'équipe.
     *
     * @return array<string, User[]>
     */
    public function getCongeCalendarForRange(Entreprise $entreprise, \DateTimeImmutable $start, \DateTimeImmutable $end): array
    {
        $conges = $this->entityManager->getRepository(Conge::class)->createQueryBuilder('c')
            ->join('c.employee', 'e')->addSelect('e')
            ->where('c.entreprise = :entreprise')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.dateDebut <= :end')
            ->andWhere('c.dateFin >= :start')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Conge::STATUS_VALIDE)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getResult();

        $byDay = [];
        foreach ($conges as $conge) {
            $cursor = max($conge->getDateDebut(), $start);
            $limite = min($conge->getDateFin(), $end);
            while ($cursor <= $limite) {
                $byDay[$cursor->format('Y-m-d')][] = $conge->getEmployee();
                $cursor = $cursor->modify('+1 day');
            }
        }

        return $byDay;
    }

    private function getWeekStartFor(\DateTimeImmutable $date): \DateTimeImmutable
    {
        return $date->format('N') == 7
            ? $date->modify('last monday')->setTime(0, 0, 0)
            : $date->modify('monday this week')->setTime(0, 0, 0);
    }

    private function getFrenchDayName(\DateTimeImmutable $date): string
    {
        $days = ['monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi', 'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'];

        return $days[strtolower($date->format('l'))];
    }
}

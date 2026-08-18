<?php
namespace App\Service;

use App\Entity\User;
use App\Entity\Pointage;
use App\Entity\Planning;
use App\Entity\Entreprise;
use Doctrine\ORM\EntityManagerInterface;

class PlanningService
{
    public function __construct(private EntityManagerInterface $em) {}

    public function getTodayData(User $user): array
    {
        $today = new \DateTimeImmutable('today');
        $entreprise = $user->getEntreprise();
        $weekStart = $this->getWeekStart($today);
        $dayName = $this->getFrenchDay($today);

        $planning = $this->em->getRepository(Planning::class)->findOneBy([
            'user' => $user,
            'weekStart' => $weekStart,
            'dayOfWeek' => $dayName
        ]);

        $pointage = $this->getPointageForDay($user, $entreprise, $today);
        $isOnPause = $pointage?->isOnPause() ?? false;

        return [
            'planning' => $planning,
            'pointage' => $pointage,
            'canPointerEntree' => $planning?->isTravail() && (!$pointage || !$pointage->getHeureEntree()),
            'canPointerSortie' => $planning?->isTravail() && $pointage && $pointage->getHeureEntree() && !$pointage->getHeureSortie(),
            'canDebutPause' => $planning?->isTravail() && $pointage && $pointage->getHeureEntree() && !$pointage->getHeureSortie() && !$isOnPause,
            'canFinPause' => $planning?->isTravail() && $pointage && $isOnPause,
            'isOnPause' => $isOnPause,
            'pauseDuree' => $this->formatMinutes($this->getPauseDurationMinutes($pointage)),
            'pauseSeconds' => $this->getPauseDurationSeconds($pointage),
            'message' => $this->getMessageJour($planning),
            'retardMinutes' => $this->calculateRetard($planning, $pointage, $entreprise),
            'heuresTravaillees' => $this->formatMinutes($this->calculateMinutesTravail($pointage)),
        ];
    }

    public function getWeekSummary(User $user): array
    {
        $weekStart = $this->getWeekStart();
        $entreprise = $user->getEntreprise();

        $plannings = $this->em->getRepository(Planning::class)->findBy(['user' => $user, 'weekStart' => $weekStart]);
        $pointages = $this->getWeekPointages($user, $entreprise, $weekStart);

        $stats = ['travail' => 0, 'repos' => 0, 'conge' => 0, 'ferie' => 0, 'minutesPrevues' => 0];
        foreach ($plannings as $p) {
            match($p->getTypeJour()) {
                Planning::TYPE_TRAVAIL => $stats['travail']++,
                Planning::TYPE_REPOS => $stats['repos']++,
                Planning::TYPE_CONGE => $stats['conge']++,
                Planning::TYPE_FERIE => $stats['ferie']++,
                default => null,
            };
            $stats['minutesPrevues'] += $p->getDureeMinutes();
        }

        $minutesReelles = array_sum(array_map(fn(Pointage $p) => $this->calculateMinutesTravail($p), $pointages));
        $minutesPause = array_sum(array_map(fn(Pointage $p) => $this->getPauseDurationMinutes($p), $pointages));
        $heuresSup = max(0, $minutesReelles - $stats['minutesPrevues']);

        return [
            'planningsSemaine' => $plannings,
            'joursTravailles' => $stats['travail'],
            'joursConges' => $stats['conge'],
            'joursRepos' => $stats['repos'],
            'joursFeries' => $stats['ferie'],
            'totalHeuresSemaine' => $this->formatMinutes($stats['minutesPrevues']),
            'totalHeuresReelles' => $this->formatMinutes($minutesReelles),
            'totalPauseHeures' => $this->formatMinutes($minutesPause),
            'totalHeuresSup' => $this->formatMinutes($heuresSup),
            'historique' => $this->em->getRepository(Pointage::class)->findBy(['employee' => $user, 'entreprise' => $entreprise], ['date' => 'DESC'], 10),
        ];
    }

    public function pointerEntree(User $user): void
    {
        $today = new \DateTimeImmutable('today');
        $pointage = $this->em->getRepository(Pointage::class)->findOneBy(['employee' => $user, 'date' => $today]) ?? new Pointage();

        if (!$pointage->getId()) {
            $pointage->setEmployee($user);
            $pointage->setDate($today);
            if ($user->getEntreprise()) {
                $pointage->setEntreprise($user->getEntreprise());
            }

            $planning = $this->em->getRepository(Planning::class)->findOneBy([
                'user' => $user,
                'weekStart' => $this->getWeekStart($today),
                'dayOfWeek' => $this->getFrenchDay($today)
            ]);

            if ($planning) {
                $pointage->setHeurePrevueDebut($planning->getHeureDebut());
                $pointage->setHeurePrevueFin($planning->getHeureFin());
                $pointage->setPausePrevueMinutes($planning->getPauseMinutes() ?? 0);
            }
        }

        if (!$pointage->getHeureEntree()) {
            $pointage->setHeureEntree(new \DateTimeImmutable('now'));
            $pointage->setStatut('present');

            $this->em->persist($pointage);
            $this->em->flush();
        }
    }

    public function pointerSortie(User $user): void
    {
        $pointage = $this->getPointageForDay($user, $user->getEntreprise(), new \DateTimeImmutable('today'));
        if ($pointage && !$pointage->getHeureSortie()) {
            $pointage->setHeureSortie(new \DateTimeImmutable());
            $this->em->flush();
        }
    }

    private function getPointageForDay(User $u, Entreprise $e, \DateTimeInterface $d): ?Pointage
    {
        return $this->em->getRepository(Pointage::class)->findOneBy(['employee' => $u, 'entreprise' => $e, 'date' => $d]);
    }

    private function getWeekPointages(User $u, Entreprise $e, \DateTimeImmutable $weekStart): array
    {
        $weekEnd = (clone $weekStart)->modify('+6 days')->setTime(23, 59, 59);
        return $this->em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.employee = :u')
            ->andWhere('p.date >= :d')
            ->andWhere('p.date <= :fin')
            ->andWhere('p.entreprise = :e')
            ->setParameter('u', $u)
            ->setParameter('d', $weekStart)
            ->setParameter('fin', $weekEnd)
            ->setParameter('e', $e)
            ->getQuery()->getResult();
    }

    private function getWeekStart(?\DateTimeImmutable $date = null): \DateTimeImmutable
    {
        $date ??= new \DateTimeImmutable('today');
        return $date->format('N') == 7 ? $date->modify('last monday')->setTime(0,0,0) : $date->modify('monday this week')->setTime(0,0,0);
    }

    private function getFrenchDay(\DateTimeInterface $date): string
    {
        $days = ['monday' => 'lundi', 'tuesday' => 'mardi', 'wednesday' => 'mercredi', 'thursday' => 'jeudi', 'friday' => 'vendredi', 'saturday' => 'samedi', 'sunday' => 'dimanche'];
        return $days[strtolower($date->format('l'))];
    }

    private function getMessageJour(?Planning $p): ?string
    {
        return match($p?->getTypeJour()) {
            Planning::TYPE_REPOS => "Repos",
            Planning::TYPE_CONGE => "En congé",
            Planning::TYPE_FERIE => "Férié",
            default => null,
        };
    }

    private function calculateRetard(?Planning $p, ?Pointage $pt, Entreprise $e): int
    {
        if (!$p?->getHeureDebut() || !$pt?->getHeureEntree()) return 0;
        $tolerance = $e->getToleranceRetard() ?? 15;
        $today = new \DateTimeImmutable('today');

        // Use DateTime instead of modifying date directly on immutable without reassignment
        $heureDebutAuj = $p->getHeureDebut();
        $heureDebutDateTime = (new \DateTimeImmutable())->setTime(
            (int)$heureDebutAuj->format('H'),
            (int)$heureDebutAuj->format('i'),
            (int)$heureDebutAuj->format('s')
        );
        $heureLimite = $heureDebutDateTime->modify("+$tolerance minutes");

        return $pt->getHeureEntree() > $heureLimite ? (int)(($pt->getHeureEntree()->getTimestamp() - $heureLimite->getTimestamp()) / 60) : 0;
    }

    //private function calculateMinutesTravail(?Pointage $p): float
    //{
    //    if (!$p || !$p->getHeureEntree()) {
    //        return 0;
    //    }
    //
    //    $sortie = $p->getHeureSortie() ?? new \DateTimeImmutable();
     //   $workedMinutes = ($sortie->getTimestamp() - $p->getHeureEntree()->getTimestamp()) / 60;
    //    $pauseMinutes = $this->getPauseDurationMinutes($p);

    //    return max(0, $workedMinutes - $pauseMinutes);
    //}

    private function calculateMinutesTravail(?Pointage $p): float
    {
        if (!$p || !$p->getHeureEntree()) {
            return 0;
        }

        $today = new \DateTimeImmutable('today');
        $pointageDate = $p->getDate() ? $p->getDate()->setTime(0, 0, 0) : null;

        // If there is no exit time:
        if (!$p->getHeureSortie()) {
            // If the pointage is NOT from today (e.g. an old forgotten shift),
            // do NOT use "now" as the exit time, otherwise it counts months of elapsed time!
            if ($pointageDate && $pointageDate < $today) {
                return 0;
            }

            // If it IS today, use current time for live tracking
            $sortie = new \DateTimeImmutable();
        } else {
            $sortie = $p->getHeureSortie();
        }

        $workedMinutes = ($sortie->getTimestamp() - $p->getHeureEntree()->getTimestamp()) / 60;

        // Safety cap: A single normal work shift cannot exceed 24 hours (1440 minutes)
        if ($workedMinutes > 1440 || $workedMinutes < 0) {
            return 0;
        }

        $pauseMinutes = $this->getPauseDurationMinutes($p);

        return max(0, $workedMinutes - $pauseMinutes);
    }
    private function getPauseDurationMinutes(?Pointage $p): int
    {
        if (!$p) {
            return 0;
        }

        $pauseMinutes = $p->getPauseDurationMinutes() ?? 0;
        if ($p->isOnPause() && $p->getHeureDebutPause()) {
            $pauseMinutes += (int) floor(((new \DateTimeImmutable())->getTimestamp() - $p->getHeureDebutPause()->getTimestamp()) / 60);
        }

        return max(0, $pauseMinutes);
    }

    private function getPauseDurationSeconds(?Pointage $p): int
    {
        if (!$p) {
            return 0;
        }

        $pauseSeconds = ($p->getPauseDurationMinutes() ?? 0) * 60;
        if ($p->isOnPause() && $p->getHeureDebutPause()) {
            $pauseSeconds += max(0, (int) ((new \DateTimeImmutable())->getTimestamp() - $p->getHeureDebutPause()->getTimestamp()));
        }

        return max(0, $pauseSeconds);
    }

    private function formatMinutes(float $minutes): string
    {
        return sprintf('%dh %02dmin', floor($minutes / 60), $minutes % 60);
    }

    public function pointerDebutPause(User $user): void
    {
        $today = new \DateTimeImmutable('today');
        $pointage = $this->getPointageForDay($user, $user->getEntreprise(), $today);

        if ($pointage && $pointage->getHeureEntree() && !$pointage->getHeureSortie() && $pointage->getHeureDebutPause() === null) {
            $pointage->setHeureDebutPause(new \DateTimeImmutable('now'));
            $this->em->flush();
        }
    }

    public function pointerFinPause(User $user): void
    {
        $today = new \DateTimeImmutable('today');
        $pointage = $this->getPointageForDay($user, $user->getEntreprise(), $today);

        if ($pointage && $pointage->getHeureDebutPause() !== null && $pointage->getHeureFinPause() === null) {
            $finPause = new \DateTimeImmutable('now');
            $pauseMinutes = (int) floor(($finPause->getTimestamp() - $pointage->getHeureDebutPause()->getTimestamp()) / 60);
            $pointage->setPauseDurationMinutes(($pointage->getPauseDurationMinutes() ?? 0) + max(0, $pauseMinutes));
            $pointage->setHeureDebutPause(null);
            $pointage->setHeureFinPause(null);
            $this->em->flush();
        }
    }
}

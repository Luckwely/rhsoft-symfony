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

    private function localNow(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('now');
    }

    private function localToday(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('today');
    }

    public function getTodayData(User $user): array
    {
        $today = $this->localToday();
        $entreprise = $user->getEntreprise();
        $weekStart = $this->getWeekStart($today);
        $dayName = $this->getFrenchDay($today);

        $planning = $this->em->getRepository(Planning::class)->findOneBy([
            'user' => $user,
            'weekStart' => $weekStart,
            'dayOfWeek' => $dayName
        ]);
        $pointage = $this->getPointageForDay($user, $entreprise, $today);
        $isWorkDay = $planning?->isTravail() === true;
        $hasEntry = $pointage?->getHeureEntree() !== null;
        $tolerance = $entreprise?->getToleranceRetard() ?? 15;
        $now = $this->localNow();
        $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        $startMinutes = $planning?->getHeureDebut() !== null ? $this->timeToMinutes($planning->getHeureDebut()) : null;
        $deadlineMinutes = $startMinutes !== null ? $startMinutes + max(0, (int) $tolerance) : null;
        $lateDeadlinePassed = $deadlineMinutes !== null && $nowMinutes > $deadlineMinutes;

        return [
            'planning' => $planning,
            'pointage' => $pointage,
            'canPointerEntree' => $isWorkDay && !$hasEntry && !$lateDeadlinePassed,
            'canPointerSortie' => $isWorkDay && $hasEntry && !$pointage->getHeureSortie(),
            'lateDeadlinePassed' => $lateDeadlinePassed,
            'heureLimitePointage' => $deadlineMinutes !== null ? sprintf('%02d:%02d', intdiv($deadlineMinutes, 60) % 24, $deadlineMinutes % 60) : null,
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
        $heuresSup = max(0, $minutesReelles - $stats['minutesPrevues']);

        return [
            'planningsSemaine' => $plannings,
            'joursTravailles' => $stats['travail'],
            'joursConges' => $stats['conge'],
            'joursRepos' => $stats['repos'],
            'joursFeries' => $stats['ferie'],
            'totalHeuresSemaine' => $this->formatMinutes($stats['minutesPrevues']),
            'totalHeuresReelles' => $this->formatMinutes($minutesReelles),
            'totalHeuresSup' => $this->formatMinutes($heuresSup),
            'historique' => $this->em->getRepository(Pointage::class)->findBy(['employee' => $user, 'entreprise' => $entreprise], ['date' => 'DESC'], 10),
        ];
    }

    public function pointerEntree(User $user): void
    {
        $today = $this->localToday();
        $entreprise = $user->getEntreprise();
        $planning = $this->em->getRepository(Planning::class)->findOneBy([
            'user' => $user,
            'weekStart' => $this->getWeekStart($today),
            'dayOfWeek' => $this->getFrenchDay($today)
        ]);

        if (!$planning || !$planning->isTravail()) {
            throw new \RuntimeException('Aucun début de poste n’est prévu pour vous aujourd’hui.');
        }

        if (!$planning->getHeureDebut()) {
            throw new \RuntimeException('L’heure de début n’est pas configurée dans votre planning.');
        }

        $tolerance = $entreprise?->getToleranceRetard() ?? 15;
        $deadlineMinutes = $this->timeToMinutes($planning->getHeureDebut()) + max(0, (int) $tolerance);
        $now = $this->localNow();
        $nowMinutes = ((int) $now->format('H')) * 60 + (int) $now->format('i');
        if ($nowMinutes > $deadlineMinutes) {
            throw new \RuntimeException(sprintf(
                'La tolérance de retard est dépassée. Le pointage d’entrée était possible jusqu’à %s.',
                sprintf('%02d:%02d', intdiv($deadlineMinutes, 60) % 24, $deadlineMinutes % 60)
            ));
        }

        $pointage = $this->getPointageForDay($user, $entreprise, $today) ?? new Pointage();
        if (!$pointage->getId()) {
            $pointage->setEmployee($user);
            $pointage->setEntreprise($entreprise);
            $pointage->setDate($today);
        }
        if ($pointage->getHeureEntree()) {
            return;
        }

        $pointage->setHeurePrevueDebut($planning->getHeureDebut());
        $pointage->setHeurePrevueFin($planning->getHeureFin());
        $pointage->setHeureEntree($now);
        $pointage->setStatut('present');
        $this->em->persist($pointage);
        $this->em->flush();
    }

    public function pointerSortie(User $user): void
    {
        $pointage = $this->getPointageForDay($user, $user->getEntreprise(), $this->localToday());
        if ($pointage && !$pointage->getHeureSortie()) {
            $pointage->setHeureSortie($this->localNow());
            $this->em->flush();
        }
    }

    private function getPointageForDay(User $u, ?Entreprise $e, \DateTimeInterface $d): ?Pointage
    {

        if (!$e) {
            return null;
        }

        return $this->em->getRepository(Pointage::class)->findOneBy(['employee' => $u, 'entreprise' => $e, 'date' => $d]);
    }

    private function getWeekPointages(User $u, ?Entreprise $e, \DateTimeImmutable $weekStart): array
    {
        if (!$e) {
            return [];
        }

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
        $date ??= $this->localToday();
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

    private function timeToMinutes(\DateTimeInterface $time): int
    {
        return ((int) $time->format('H')) * 60 + (int) $time->format('i');
    }

    private function calculateRetard(?Planning $p, ?Pointage $pt, ?Entreprise $e): int
    {
        if (!$e || !$p?->getHeureDebut() || !$pt?->getHeureEntree()) return 0;
        $tolerance = $e->getToleranceRetard() ?? 15;

        $heureLimite = $p->getHeureDebut()->modify("+$tolerance minutes");

        return $pt->getHeureEntree() > $heureLimite ? (int)(($pt->getHeureEntree()->getTimestamp() - $heureLimite->getTimestamp()) / 60) : 0;
    }

    private function calculateMinutesTravail(?Pointage $p): float
    {
        if (!$p || !$p->getHeureEntree()) {
            return 0;
        }

        $today = $this->localToday();
        $pointageDate = $p->getDate() ? $p->getDate()->setTime(0, 0, 0) : null;

        if (!$p->getHeureSortie()) {
            if ($pointageDate && $pointageDate < $today) {
                return 0;
            }

            $now = $this->localNow();
            $sortie = $p->getHeureEntree()->setTime((int) $now->format('H'), (int) $now->format('i'), (int) $now->format('s'));
        } else {
            $sortie = $p->getHeureSortie();
        }

        $workedSeconds = $sortie->getTimestamp() - $p->getHeureEntree()->getTimestamp();

        if ($workedSeconds < 0) {
            $workedSeconds += 86400;
        }

        $workedMinutes = $workedSeconds / 60;

        if ($workedMinutes > 1440) {
            return 0;
        }

        return max(0, $workedMinutes);
    }

    private function formatMinutes(float $minutes): string
    {
        return sprintf('%dh %02dmin', floor($minutes / 60), $minutes % 60);
    }
}

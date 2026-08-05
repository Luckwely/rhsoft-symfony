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

        return [ // <- IL MANQUAIT ÇA
            'planning' => $planning,
            'pointage' => $pointage,
            'canPointerEntree' => $planning?->isTravail() && !$pointage,
            'canPointerSortie' => $planning?->isTravail() && $pointage && !$pointage->getHeureSortie(),
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
        $today = new \DateTimeImmutable('today');
        $entreprise = $user->getEntreprise();
        $pointage = $this->getPointageForDay($user, $entreprise, $today) ?? new Pointage();

        $pointage->setEmployee($user)->setEntreprise($entreprise)->setDate($today)->setHeureEntree(new \DateTimeImmutable());
        $this->em->persist($pointage); $this->em->flush();
    }

    public function pointerSortie(User $user): void
    {
        $pointage = $this->getPointageForDay($user, $user->getEntreprise(), new \DateTimeImmutable('today'));
        if($pointage && !$pointage->getHeureSortie()){
            $pointage->setHeureSortie(new \DateTimeImmutable()); $this->em->flush();
        }
    }

    private function getPointageForDay(User $u, Entreprise $e, \DateTimeInterface $d): ?Pointage
    {
        return $this->em->getRepository(Pointage::class)->findOneBy(['employee' => $u, 'entreprise' => $e, 'date' => $d]);
    }
    private function getWeekPointages(User $u, Entreprise $e, \DateTimeInterface $weekStart): array
    {
        $weekEnd = $weekStart->modify('+6 days')->setTime(23,59,59); // <- AJOUTE ÇA
        return $this->em->getRepository(Pointage::class)->createQueryBuilder('p')
            ->where('p.employee = :u')
            ->andWhere('p.date >= :d')->andWhere('p.date <= :fin') // <- AJOUTE ÇA
            ->andWhere('p.entreprise = :e')
            ->setParameter('u', $u)->setParameter('d', $weekStart)->setParameter('fin', $weekEnd)->setParameter('e', $e)
            ->getQuery()->getResult();
    }
    private function getWeekStart(?\DateTimeInterface $date = null): \DateTimeImmutable
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
        return match($p?->getTypeJour()){
            Planning::TYPE_REPOS => "Repos",
            Planning::TYPE_CONGE => "En congé",
            Planning::TYPE_FERIE => "Férié",
            default => null,
        };
    }

    private function calculateRetard(?Planning $p, ?Pointage $pt, Entreprise $e): int
    {
        if(!$p?->getHeureDebut() || !$pt?->getHeureEntree()) return 0;
        $tolerance = $e->getToleranceRetard() ?? 15;
        $today = new \DateTimeImmutable('today');
        $heureDebutAuj = $p->getHeureDebut()->setDate((int)$today->format('Y'),(int)$today->format('m'),(int)$today->format('d'));
        $heureLimite = $heureDebutAuj->modify("+$tolerance minutes");

        return $pt->getHeureEntree() > $heureLimite ? (int)(($pt->getHeureEntree()->getTimestamp() - $heureLimite->getTimestamp()) / 60) : 0;
    }

    private function calculateMinutesTravail(?Pointage $p): float
    {
        if(!$p || !$p->getHeureEntree()) return 0;
        $sortie = $p->getHeureSortie() ?? new \DateTimeImmutable();
        return ($sortie->getTimestamp() - $p->getHeureEntree()->getTimestamp()) / 60;
    }
    private function formatMinutes(float $minutes): string
    {
        return sprintf('%dh %02dmin', floor($minutes / 60), $minutes % 60);
    }
}

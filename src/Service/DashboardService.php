<?php

namespace App\Service;

use App\Entity\Entreprise;
use App\Entity\User;
use App\Repository\AvanceSalaireRepository;
use App\Repository\CongeRepository;
use App\Repository\DemissionRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\PaieRepository;
use App\Repository\PageViewRepository;
use App\Repository\PointageRepository;
use App\Repository\UserRepository;

final class DashboardService
{
    private const ADVANCED_ANALYTICS_LOOKBACK_DAYS = 90;

    public function __construct(
        private PointageRepository $pointageRepository,
        private UserRepository $userRepository,
        private CongeRepository $congeRepository,
        private PaieRepository $paieRepository,
        private EntrepriseRepository $entrepriseRepository,
        private DemissionRepository $demissionRepository,
        private PlanningService $planningService,
        private PageViewRepository $pageViewRepository,
        private AvanceSalaireRepository $avanceSalaireRepository
    ) {
    }

    public function getAdminDashboardData(User $user): array
    {
        $entreprise = $user->getEntreprise();

        if (!$entreprise) {
            return [
                'totalEmployees' => 0,
                'presentToday' => 0,
                'pendingLeaves' => 0,
                'payrollMass' => 0.0,
                'services' => [],
                'serviceLabels' => [],
                'serviceCounts' => [],
                'recentPointages' => [],
                'recentActivity' => [],
                'alertContractsExpiring' => 0,
                'alertProbation' => 0,
                'alertAnnualReviews' => 0,
                'advancedAnalytics' => $this->emptyAdvancedAnalytics(),
            ];
        }

        $today = new \DateTimeImmutable('today');
        $pointageStats = $this->pointageRepository->getStatsByDate($today, $entreprise);
        $services = $this->userRepository->countByServiceAndEntreprise($entreprise);
        $serviceLabels = array_column($services, 'service');
        $serviceCounts = array_map(static fn(array $row): int => (int) $row['total'], $services);

        return [
            'totalEmployees' => $this->userRepository->countByEntreprise($entreprise),
            'presentToday' => (int) ($pointageStats['present'] ?? 0) + (int) ($pointageStats['retard'] ?? 0),
            'pendingLeaves' => $this->congeRepository->countPendingByEntreprise($entreprise),
            'payrollMass' => $this->paieRepository->sumSalaireBrutByEntrepriseAndMonth($entreprise, (int) $today->format('m'), (int) $today->format('Y')),
            'services' => $services,
            'serviceLabels' => $serviceLabels,
            'serviceCounts' => $serviceCounts,
            'recentPointages' => $this->pointageRepository->findLatestByEntreprise($entreprise, 8),
            'recentActivity' => $this->getRecentActivity($entreprise),
            'alertContractsExpiring' => $this->userRepository->countContractsExpiringSoon($entreprise),
            'alertProbation' => $this->userRepository->countProbationEnding($entreprise),
            'alertAnnualReviews' => $this->userRepository->countEmployeesForAnnualReview($entreprise),
            'advancedAnalytics' => $this->getAdvancedAnalytics($entreprise),
            'advancedAnalyticsLookbackDays' => self::ADVANCED_ANALYTICS_LOOKBACK_DAYS,
        ];
    }

    /**
     * Builds a unified, real "recent activity" feed for the dashboard by merging
     * several genuine event sources (clock-ins/lateness, validated leave requests,
     * new hires) and sorting them by recency. Replaces what used to be a block of
     * hardcoded fictional names in the template.
     */
    private function getRecentActivity(Entreprise $entreprise, int $limit = 5): array
    {
        $activities = [];

        foreach ($this->pointageRepository->findLatestByEntreprise($entreprise, 10) as $pointage) {
            $employee = $pointage->getEmployee();
            if (!$employee || !$pointage->getHeureEntree()) {
                continue;
            }

            if ($pointage->getStatut() === 'retard') {
                $activities[] = [
                    'employee' => $employee,
                    'message' => sprintf('retard de %d min', $pointage->getMinutesRetard()),
                    'badgeLabel' => 'Retard',
                    'badgeClass' => 'bg-danger bg-opacity-10 text-danger',
                    'icon' => 'exclamation-triangle',
                    'iconBg' => 'bg-danger',
                    'timestamp' => $pointage->getHeureEntree(),
                ];
            } elseif ($pointage->getStatut() === 'present') {
                $activities[] = [
                    'employee' => $employee,
                    'message' => 'a pointé à ' . $pointage->getHeureEntree()->format('H:i'),
                    'badgeLabel' => "À l'heure",
                    'badgeClass' => 'bg-success bg-opacity-10 text-success',
                    'icon' => 'clock',
                    'iconBg' => 'bg-primary',
                    'timestamp' => $pointage->getHeureEntree(),
                ];
            }
        }

        foreach ($this->congeRepository->findRecentlyValidatedByEntreprise($entreprise, 5) as $conge) {
            if (!$conge->getEmployee() || !$conge->getValideLe()) {
                continue;
            }

            $activities[] = [
                'employee' => $conge->getEmployee(),
                'message' => 'demande de congé validée',
                'badgeLabel' => 'Validé',
                'badgeClass' => 'bg-success bg-opacity-10 text-success',
                'icon' => 'check-circle',
                'iconBg' => 'bg-success',
                'timestamp' => $conge->getValideLe(),
            ];
        }

        foreach ($this->userRepository->findRecentHiresByEntreprise($entreprise, 5) as $employee) {
            if (!$employee->getCreatedAt()) {
                continue;
            }

            $activities[] = [
                'employee' => $employee,
                'message' => 'nouvelle arrivée (' . ($employee->getPoste() ?: 'Employé') . ')',
                'badgeLabel' => 'Nouveau',
                'badgeClass' => 'bg-warning bg-opacity-10 text-warning',
                'icon' => 'person-add',
                'iconBg' => 'bg-warning',
                'timestamp' => $employee->getCreatedAt(),
            ];
        }

        usort($activities, static fn(array $a, array $b): int => $b['timestamp'] <=> $a['timestamp']);

        return array_slice($activities, 0, $limit);
    }

    public function getRhDashboardData(User $user): array
    {
        $entreprise = $user->getEntreprise();

        if (!$entreprise) {
            return [
                'totalEmployees' => 0,
                'pendingLeaves' => 0,
                'absentToday' => 0,
                'lateToday' => 0,
                'presentToday' => 0,
                'services' => [],
                'recentPointages' => [],
                'recentActivity' => [],
                'monPointage' => null,
            ];
        }

        $today = new \DateTimeImmutable('today');
        $pointageStats = $this->pointageRepository->getStatsByDate($today, $entreprise);
        $services = $this->userRepository->countByServiceAndEntreprise($entreprise);

        return [
            'totalEmployees' => $this->userRepository->countByEntreprise($entreprise),
            'pendingLeaves' => $this->congeRepository->countPendingByEntreprise($entreprise),
            'absentToday' => (int) ($pointageStats['absent'] ?? 0),
            'lateToday' => (int) ($pointageStats['retard'] ?? 0),
            'presentToday' => (int) ($pointageStats['present'] ?? 0),
            'services' => $services,
            'recentPointages' => $this->pointageRepository->findLatestByEntreprise($entreprise, 6),
            'recentActivity' => $this->getRecentActivity($entreprise),
            // Pointage personnel du RH (démarrer/terminer son propre shift pour être payé).
            'monPointage' => ($todayData = $this->planningService->getTodayData($user))['pointage'],
            'planning' => $todayData['planning'],
            'canPointerEntree' => $todayData['canPointerEntree'],
            'canPointerSortie' => $todayData['canPointerSortie'],
            'lateDeadlinePassed' => $todayData['lateDeadlinePassed'],
            'heureLimitePointage' => $todayData['heureLimitePointage'],
            'message' => $todayData['message'],
            'retardMinutes' => $todayData['retardMinutes'],
            'heuresTravaillees' => $todayData['heuresTravaillees'],
        ];
    }

    public function getAdminReportingData(User $user, ?int $year = null): array
    {
        $entreprise = $user->getEntreprise();
        $year = $year ?: (int) (new \DateTimeImmutable('today'))->format('Y');

        if (!$entreprise) {
            return [
                'year' => $year,
                'totalEmployees' => 0,
                'hires' => 0,
                'departures' => 0,
                'turnoverRate' => 0.0,
                'absentRate' => 0.0,
                'payrollMass' => 0.0,
                'serviceDistribution' => [],
                'chartLabels' => [],
                'chartValues' => [],
                'absenceMotifs' => [],
                'serviceCosts' => [],
                'overtimeList' => [],
                'totalOvertimeHours' => 0,
                'advancedAnalytics' => $this->emptyAdvancedAnalytics(),
            ];
        }

        $totalEmployees = $this->userRepository->countByEntreprise($entreprise);
        $hires = $this->userRepository->countHiresByEntrepriseAndYear($entreprise, $year);
        $departures = $this->demissionRepository->countDeparturesByEntrepriseAndYear($entreprise, $year);
        $turnoverRate = $totalEmployees > 0 ? round(($departures / $totalEmployees) * 100, 1) : 0.0;
        $absentCount = $this->pointageRepository->countAbsencesByEntrepriseAndMonth($entreprise, (int) (new \DateTimeImmutable('today'))->format('m'), $year);
        $absentRate = $totalEmployees > 0 ? round(($absentCount / $totalEmployees) * 100, 1) : 0.0;
        $serviceDistribution = $this->userRepository->countByServiceAndEntreprise($entreprise);

        $monthlyDepartures = $this->demissionRepository->getMonthlyDeparturesByEntreprise($entreprise, 6);
        $departuresByMonth = [];
        foreach ($monthlyDepartures as $row) {
            $departuresByMonth[sprintf('%04d-%02d', $row['annee'], $row['mois'])] = (int) $row['total'];
        }

        $chartLabels = [];
        $chartValues = [];
        $today = new \DateTimeImmutable('today');
        for ($i = 5; $i >= 0; $i--) {
            $date = (clone $today)->modify("-{$i} months");
            $key = $date->format('Y-m');
            $chartLabels[] = $date->format('M Y');
            $chartValues[] = $departuresByMonth[$key] ?? 0;
        }

        // Real dynamic data for sections
        $absenceMotifs = $this->congeRepository->findAbsenceMotifsStatsByEntreprise($entreprise, (int) $today->format('m'), $year);
        $serviceCosts = $this->paieRepository->getServiceCostsByEntrepriseAndMonth($entreprise, (int) $today->format('m'), $year);
        $overtimeList = $this->pointageRepository->findTopOvertimeByEntreprise($entreprise, 5);
        $totalOvertimeHours = array_sum(array_column($overtimeList, 'hours')) ?: 0;

        return [
            'year' => $year,
            'totalEmployees' => $totalEmployees,
            'hires' => $hires,
            'departures' => $departures,
            'turnoverRate' => $turnoverRate,
            'absentRate' => $absentRate,
            'payrollMass' => $this->paieRepository->sumSalaireBrutByEntrepriseAndMonth($entreprise, (int) $today->format('m'), $year),
            'serviceDistribution' => $serviceDistribution,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'absenceMotifs' => $absenceMotifs,
            'serviceCosts' => $serviceCosts,
            'overtimeList' => $overtimeList,
            'totalOvertimeHours' => $totalOvertimeHours,
            'advancedAnalytics' => $this->getAdvancedAnalytics($entreprise),
            'advancedAnalyticsLookbackDays' => self::ADVANCED_ANALYTICS_LOOKBACK_DAYS,
        ];
    }

    private function emptyAdvancedAnalytics(): array
    {
        return [
            'roles' => [], 'services' => [], 'posts' => [], 'late' => [], 'absent' => [],
            'advances' => [], 'highestSalary' => null, 'lowestSalary' => null,
        ];
    }

    /** Builds the employee analytics displayed by both admin views from current records. */
    private function getAdvancedAnalytics(Entreprise $entreprise): array
    {
        $employees = $this->userRepository->findBy(['entreprise' => $entreprise]);
        $roles = $services = $posts = [];
        foreach ($employees as $employee) {
            foreach ($employee->getRoles() as $role) {
                if ($role === 'ROLE_USER') { continue; }
                $roles[$role] = ($roles[$role] ?? 0) + 1;
            }
            $service = $employee->getService() ?: 'Non renseigné';
            $post = $employee->getPoste() ?: 'Non renseigné';
            $services[$service] = ($services[$service] ?? 0) + 1;
            $posts[$post] = ($posts[$post] ?? 0) + 1;
        }
        $roleLabels = ['ROLE_ADMIN' => 'Administrateurs', 'ROLE_RH' => 'Ressources humaines', 'ROLE_MANAGER' => 'Managers', 'ROLE_EMPLOYE' => 'Employés'];
        $toRows = static function (array $values, array $labels = []): array {
            arsort($values);
            return array_map(static fn(string $label, int $total): array => ['label' => $labels[$label] ?? $label, 'total' => $total], array_keys($values), array_values($values));
        };

        $late = []; $absent = [];
        $since = (new \DateTimeImmutable('today'))->modify('-'.self::ADVANCED_ANALYTICS_LOOKBACK_DAYS.' days');
        foreach ($this->pointageRepository->findByEntrepriseSince($entreprise, $since, 500) as $pointage) {
            $employee = $pointage->getEmployee();
            if (!$employee) { continue; }
            $id = (string) $employee->getId();
            if ($pointage->getStatutEffectif() === 'retard') {
                $late[$id] ??= ['employee' => $employee, 'total' => 0]; $late[$id]['total']++;
            }
            if ($pointage->getStatutEffectif() === 'absent') {
                $absent[$id] ??= ['employee' => $employee, 'total' => 0]; $absent[$id]['total']++;
            }
        }
        $sortEmployees = static function (array $rows): array {
            usort($rows, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
            return array_slice(array_values($rows), 0, 5);
        };
        $advances = [];
        foreach ($this->avanceSalaireRepository->findByEntrepriseSince($entreprise, $since, 500) as $avance) {
            $employee = $avance->getEmployee();
            if (!$employee) { continue; }
            $id = (string) $employee->getId();
            $advances[$id] ??= ['employee' => $employee, 'total' => 0.0, 'count' => 0];
            $advances[$id]['total'] += (float) ($avance->getMontant() ?? 0); $advances[$id]['count']++;
        }
        usort($advances, static fn(array $a, array $b): int => $b['total'] <=> $a['total']);
        $salaried = array_values(array_filter($employees, static fn(User $e): bool => $e->getSalaireBase() !== null));
        usort($salaried, static fn(User $a, User $b): int => $b->getSalaireBase() <=> $a->getSalaireBase());
        $highest = $salaried[0] ?? null; $lowest = $salaried ? $salaried[array_key_last($salaried)] : null;
        return [
            'roles' => $toRows($roles, $roleLabels), 'services' => $toRows($services), 'posts' => $toRows($posts),
            'late' => $sortEmployees($late), 'absent' => $sortEmployees($absent), 'advances' => array_slice($advances, 0, 5),
            'highestSalary' => $highest ? ['employee' => $highest, 'amount' => $highest->getSalaireBase()] : null,
            'lowestSalary' => $lowest ? ['employee' => $lowest, 'amount' => $lowest->getSalaireBase()] : null,
        ];
    }

    public function getRhReportData(User $user, ?int $year = null): array
    {
        $entreprise = $user->getEntreprise();
        $year = $year ?: (int) (new \DateTimeImmutable('today'))->format('Y');

        if (!$entreprise) {
            return [
                'year' => $year,
                'totalEmployees' => 0,
                'hires' => 0,
                'departures' => 0,
                'turnoverRate' => 0.0,
                'presentToday' => 0,
                'absentToday' => 0,
                'pendingLeaves' => 0,
                'serviceDistribution' => [],
                'chartLabels' => [],
                'chartValues' => [],
                'ancienneteMoyenne' => null,
                'masseSalariale' => 0.0,
                'masseSalarialeEvolution' => null,
                'tauxAbsenteisme' => 0.0,
                'tauxAbsenteismeEvolution' => null,
                'effectifEvolution' => null,
                'hiresEvolution' => null,
                'departuresEvolution' => null,
                'genreDistribution' => ['H' => 0, 'F' => 0, 'non_renseigne' => 0],
                'ageDistribution' => ['<25' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0],
                'ageDistributionLabels' => ['<25', '25-34', '35-44', '45-54', '55+'],
                'ageDistributionValues' => [0, 0, 0, 0, 0],
                'ageDistributionWithoutBirthdate' => 0,
            ];
        }

        $today = new \DateTimeImmutable('today');
        $pointageStats = $this->pointageRepository->getStatsByDate($today, $entreprise);
        $totalEmployees = $this->userRepository->countByEntreprise($entreprise);
        $hires = $this->userRepository->countHiresByEntrepriseAndYear($entreprise, $year);
        $departures = $this->demissionRepository->countDeparturesByEntrepriseAndYear($entreprise, $year);
        $turnoverRate = $totalEmployees > 0 ? round(($departures / $totalEmployees) * 100, 1) : 0.0;
        $serviceDistribution = $this->userRepository->countByServiceAndEntreprise($entreprise);

        $monthlyDepartures = $this->demissionRepository->getMonthlyDeparturesByEntreprise($entreprise, 6);
        $departuresByMonth = [];
        foreach ($monthlyDepartures as $row) {
            $departuresByMonth[sprintf('%04d-%02d', $row['annee'], $row['mois'])] = (int) $row['total'];
        }

        $chartLabels = [];
        $chartValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = (clone $today)->modify("-{$i} months");
            $key = $date->format('Y-m');
            $chartLabels[] = $date->format('M Y');
            $chartValues[] = $departuresByMonth[$key] ?? 0;
        }

        // --- Bilan Social : indicateurs calculés à partir des données réelles ---
        $previousYear = $year - 1;
        $hiresPreviousYear = $this->userRepository->countHiresByEntrepriseAndYear($entreprise, $previousYear);
        $departuresPreviousYear = $this->demissionRepository->countDeparturesByEntrepriseAndYear($entreprise, $previousYear);
        // Effectif estimé en début de période = effectif actuel - embauches + départs de l'année.
        $estimatedEffectifStartOfYear = $totalEmployees - $hires + $departures;

        $masseSalariale = $this->paieRepository->sumSalaireBrutByEntrepriseAndYear($entreprise, $year);
        $masseSalarialePreviousYear = $this->paieRepository->sumSalaireBrutByEntrepriseAndYear($entreprise, $previousYear);

        $tauxAbsenteisme = $this->pointageRepository->getAbsenteeismRateByEntrepriseAndYear($entreprise, $year);
        $tauxAbsenteismePreviousYear = $this->pointageRepository->getAbsenteeismRateByEntrepriseAndYear($entreprise, $previousYear);

        $genreDistribution = $this->userRepository->countByGenreAndEntreprise($entreprise);
        $ageDistribution = $this->userRepository->getAgeDistributionByEntreprise($entreprise);

        return [
            'year' => $year,
            'totalEmployees' => $totalEmployees,
            'hires' => $hires,
            'departures' => $departures,
            'turnoverRate' => $turnoverRate,
            'presentToday' => (int) ($pointageStats['present'] ?? 0),
            'absentToday' => (int) ($pointageStats['absent'] ?? 0),
            'pendingLeaves' => $this->congeRepository->countPendingByEntreprise($entreprise),
            'serviceDistribution' => $serviceDistribution,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'ancienneteMoyenne' => $this->userRepository->getAverageSeniorityYears($entreprise),
            'masseSalariale' => $masseSalariale,
            'masseSalarialeEvolution' => $this->percentEvolution($masseSalarialePreviousYear, $masseSalariale),
            'tauxAbsenteisme' => $tauxAbsenteisme,
            'tauxAbsenteismeEvolution' => $tauxAbsenteismePreviousYear > 0 ? round($tauxAbsenteisme - $tauxAbsenteismePreviousYear, 1) : null,
            'effectifEvolution' => $this->percentEvolution($estimatedEffectifStartOfYear, $totalEmployees),
            'hiresEvolution' => $this->percentEvolution($hiresPreviousYear, $hires),
            'departuresEvolution' => $this->percentEvolution($departuresPreviousYear, $departures),
            'genreDistribution' => $genreDistribution,
            'ageDistribution' => $ageDistribution['buckets'],
            'ageDistributionLabels' => array_keys($ageDistribution['buckets']),
            'ageDistributionValues' => array_values($ageDistribution['buckets']),
            'ageDistributionWithoutBirthdate' => $ageDistribution['withoutBirthdate'],
        ];
    }

    /**
     * Évolution en % entre une valeur de référence et une valeur actuelle.
     * Retourne null quand la comparaison n'a pas de sens (référence à 0 ou négative).
     */
    private function percentEvolution(float $previous, float $current): ?float
    {
        if ($previous <= 0) {
            return null;
        }

        return round((($current - $previous) / $previous) * 100, 1);
    }

    public function getManagerDashboardData(User $user): array
    {
        $entreprise = $user->getEntreprise();

        if (!$entreprise) {
            return [
                'teamSize' => 0,
                'presentToday' => 0,
                'absentToday' => 0,
                'lateToday' => 0,
                'pendingLeaves' => 0,
                'serviceDistribution' => [],
                'recentPointages' => [],
                'monPointage' => null,
                'planning' => null,
                'canPointerEntree' => false,
                'canPointerSortie' => false,
                'lateDeadlinePassed' => false,
                'heureLimitePointage' => null,
                'message' => null,
            ];
        }

        $today = new \DateTimeImmutable('today');
        $pointageStats = $this->pointageRepository->getStatsByDate($today, $entreprise);

        $todayData = $this->planningService->getTodayData($user);

        return [
            'teamSize' => $this->userRepository->countByEntreprise($entreprise),
            'presentToday' => (int) ($pointageStats['present'] ?? 0),
            'absentToday' => (int) ($pointageStats['absent'] ?? 0),
            'lateToday' => (int) ($pointageStats['retard'] ?? 0),
            'pendingLeaves' => $this->congeRepository->countPendingByEntreprise($entreprise),
            'serviceDistribution' => $this->userRepository->countByServiceAndEntreprise($entreprise),
            'recentPointages' => $this->pointageRepository->findLatestByEntreprise($entreprise, 6),
            'monPointage' => $todayData['pointage'],
            'planning' => $todayData['planning'],
            'canPointerEntree' => $todayData['canPointerEntree'],
            'canPointerSortie' => $todayData['canPointerSortie'],
            'lateDeadlinePassed' => $todayData['lateDeadlinePassed'],
            'heureLimitePointage' => $todayData['heureLimitePointage'],
            'message' => $todayData['message'],
        ];
    }

    public function getSuperAdminDashboardData(): array
    {
        $today = new \DateTimeImmutable('today');
        $monthlyRecurringRevenue = $this->entrepriseRepository->getMonthlyRecurringRevenue();

        $chartLabels = [];
        $chartValues = [];
        $companies = $this->entrepriseRepository->findAll();

        for ($i = 5; $i >= 0; $i--) {
            $date = (clone $today)->modify("-{$i} months");
            $chartLabels[] = $date->format('M Y');
            $monthEnd = $date->modify('last day of this month 23:59:59');
            $chartValues[] = array_reduce($companies, static function (float $total, Entreprise $company) use ($monthEnd): float {
                if ($company->getStatus() !== 'active' || !$company->getCreatedAt() || $company->getCreatedAt() > $monthEnd) {
                    return $total;
                }
                return $total + (float) ($company->getPrixMois() ?? 0);
            }, 0.0);
        }

        return [
            'ca' => $monthlyRecurringRevenue,
            'caMonth' => $monthlyRecurringRevenue,
            'totalCompanies' => $this->entrepriseRepository->countAll(),
            'activeCompanies' => $this->entrepriseRepository->countByStatus('active'),
            'totalUsers' => $this->userRepository->count([]),
            'activeSubscriptions' => $this->entrepriseRepository->countByStatus('active'),
            'trialCompanies' => $this->entrepriseRepository->countByStatus('trial'),
            'recentCompanies' => $this->entrepriseRepository->findLatest(5),
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
            'totalPageViews' => $this->pageViewRepository->countTotal(),
        ];
    }


}

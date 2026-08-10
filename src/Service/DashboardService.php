<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\CongeRepository;
use App\Repository\DemissionRepository;
use App\Repository\EntrepriseRepository;
use App\Repository\PaieRepository;
use App\Repository\PointageRepository;
use App\Repository\UserRepository;

final class DashboardService
{
    public function __construct(
        private PointageRepository $pointageRepository,
        private UserRepository $userRepository,
        private CongeRepository $congeRepository,
        private PaieRepository $paieRepository,
        private EntrepriseRepository $entrepriseRepository,
        private DemissionRepository $demissionRepository
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
                'alertContractsExpiring' => 0,
                'alertProbation' => 0,
                'alertAnnualReviews' => 0,
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
            'alertContractsExpiring' => $this->userRepository->countContractsExpiringSoon($entreprise),
            'alertProbation' => $this->userRepository->countProbationEnding($entreprise),
            'alertAnnualReviews' => $this->userRepository->countEmployeesForAnnualReview($entreprise),
        ];
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

        return [
            'year' => $year,
            'totalEmployees' => $totalEmployees,
            'hires' => $hires,
            'departures' => $departures,
            'turnoverRate' => $turnoverRate,
            'absentRate' => $absentRate,
            'payrollMass' => $this->paieRepository->sumSalaireBrutByEntrepriseAndMonth($entreprise, (int) (new \DateTimeImmutable('today'))->format('m'), $year),
            'serviceDistribution' => $serviceDistribution,
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
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
        ];
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
            ];
        }

        $today = new \DateTimeImmutable('today');
        $pointageStats = $this->pointageRepository->getStatsByDate($today, $entreprise);

        return [
            'teamSize' => $this->userRepository->countByEntreprise($entreprise),
            'presentToday' => (int) ($pointageStats['present'] ?? 0),
            'absentToday' => (int) ($pointageStats['absent'] ?? 0),
            'lateToday' => (int) ($pointageStats['retard'] ?? 0),
            'pendingLeaves' => $this->congeRepository->countPendingByEntreprise($entreprise),
            'serviceDistribution' => $this->userRepository->countByServiceAndEntreprise($entreprise),
            'recentPointages' => $this->pointageRepository->findLatestByEntreprise($entreprise, 6),
        ];
    }

    public function getSuperAdminDashboardData(): array
    {
        $today = new \DateTimeImmutable('today');
        $currentMonth = (int) $today->format('m');
        $currentYear = (int) $today->format('Y');
        $monthlyTotals = $this->paieRepository->getMonthlyGrossTotals(6);

        $totalsByMonth = [];
        foreach ($monthlyTotals as $row) {
            $key = sprintf('%04d-%02d', $row['annee'], $row['mois']);
            $totalsByMonth[$key] = (float) $row['total'];
        }

        $chartLabels = [];
        $chartValues = [];
        for ($i = 5; $i >= 0; $i--) {
            $date = (clone $today)->modify("-{$i} months");
            $key = $date->format('Y-m');
            $chartLabels[] = $date->format('M Y');
            $chartValues[] = $totalsByMonth[$key] ?? 0.0;
        }

        return [
            'caMonth' => $this->paieRepository->sumSalaireBrutByMonth($currentMonth, $currentYear),
            'totalCompanies' => $this->entrepriseRepository->countAll(),
            'activeCompanies' => $this->entrepriseRepository->countByStatus('active'),
            'totalUsers' => $this->userRepository->count([]),
            'activeSubscriptions' => $this->entrepriseRepository->countByStatus('active'),
            'trialCompanies' => $this->entrepriseRepository->countByStatus('essai'),
            'chartLabels' => $chartLabels,
            'chartValues' => $chartValues,
        ];
    }
}

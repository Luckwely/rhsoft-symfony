<?php

namespace App\Controller\Employe;

use App\Repository\PayslipRepository;
use App\Service\Contract\PayslipMetricsCalculatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class PayslipController extends AbstractController
{
    #[Route('/mes-fiches-de-paie', name: 'app_employee_payslips')]
    public function index(
        Request $request,
        PayslipRepository $payslipRepository,
        PayslipMetricsCalculatorInterface $metricsCalculator
    ): Response {
        $user = $this->getUser();
        $availableYears = $payslipRepository->getAvailableYearsForUser($user);

        // Default to the most recent available year, or current year
        $defaultYear = $availableYears[0] ?? (int) date('Y');
        $selectedYear = $request->query->getInt('year', $defaultYear);

        $payslips = $payslipRepository->findByUserAndYear($user, $selectedYear);
        $kpis = $metricsCalculator->calculateForYear($payslips);

        return $this->render('employe/paie/payslips.html.twig', [
            'payslips' => $payslips,
            'kpis' => $kpis,
            'selectedYear' => $selectedYear,
            'availableYears' => $availableYears,
        ]);
    }
}

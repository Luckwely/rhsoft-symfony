<?php

namespace App\Controller\Employe;

use App\Entity\Paie;
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

    #[Route('/mes-fiches-de-paie/download/{id}', name: 'app_employee_payslip_download', methods: ['GET'])]
    public function download(Paie $payslip): Response
    {
        // Sécurité : vérifier que la fiche appartient bien à l'employé connecté
        if ($payslip->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à accéder à cette fiche de paie.");
        }

        // TODO: Implémenter la logique de téléchargement PDF (ex: Dompdf ou TwigToPdf)
        // Pour l'instant, un retour simple pour valider le fonctionnement de la route :
        return new Response('Téléchargement de la fiche de paie n° ' . $payslip->getId());
    }
}

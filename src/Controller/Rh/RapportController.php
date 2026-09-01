<?php

namespace App\Controller\Rh;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Dompdf\Dompdf;
use Dompdf\Options;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class RapportController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/rapport', name: 'app_rh_rapport')]
    public function index(Request $request): Response
    {
        $year = $request->query->getInt('year', (int) (new \DateTimeImmutable('today'))->format('Y'));

        return $this->render('rh/rapport/index.html.twig', [
            'reportData' => $this->dashboardService->getRhReportData($this->getUser(), $year),
        ]);
    }

    #[Route('/rapport/export/pdf', name: 'app_rh_rapport_export_pdf', methods: ['GET'])]
    public function exportPdf(Request $request): Response
    {
        return $this->renderPdf($request, 'rapport-rh');
    }

    #[Route('/rapport/export/bilan-pdf', name: 'app_rh_rapport_export_bilan_pdf', methods: ['GET'])]
    public function exportBilanPdf(Request $request): Response
    {
        return $this->renderPdf($request, 'bilan-social-rh');
    }

    private function renderPdf(Request $request, string $filenamePrefix): Response
    {
        $year = $request->query->getInt('year', (int) (new \DateTimeImmutable('today'))->format('Y'));
        if ($year < 2000 || $year > 2100) {
            $year = (int) (new \DateTimeImmutable('today'))->format('Y');
        }

        $user = $this->getUser();
        $reportData = $this->dashboardService->getRhReportData($user, $year);
        $html = $this->renderView('rh/rapport/pdf.html.twig', [
            'reportData' => $reportData,
            'entreprise' => $user->getEntreprise(),
            'generatedAt' => new \DateTimeImmutable(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$filenamePrefix.'-'.$year.'.pdf"',
        ]);
    }
}

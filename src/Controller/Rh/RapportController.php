<?php

namespace App\Controller\Rh;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
}

<?php

namespace App\Controller\Admin;

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ReportingController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/reporting', name: 'app_admin_reporting')]
    public function index(Request $request): Response
    {
        $year = $request->query->getInt('year', (int) (new \DateTimeImmutable('today'))->format('Y'));

        return $this->render('admin/reporting/index.html.twig', [
            'reportData' => $this->dashboardService->getAdminReportingData($this->getUser(), $year),
        ]);
    }
}

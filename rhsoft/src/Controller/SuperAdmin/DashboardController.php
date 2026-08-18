<?php

namespace App\Controller\SuperAdmin; // Important

use App\Service\DashboardService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super-admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/dashboard', name: 'app_super_admin_dashboard')]
    public function index(): Response
    {
        return $this->render('super_admin/dashboard/index.html.twig', [
            'dashboard' => $this->dashboardService->getSuperAdminDashboardData(),
        ]);
    }
}

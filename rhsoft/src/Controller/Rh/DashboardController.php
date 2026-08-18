<?php

namespace App\Controller\Rh;

use App\Service\DashboardService;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DashboardController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/dashboard', name: 'app_rh_dashboard')]
    public function index(): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        $dashboardData = $this->dashboardService->getRhDashboardData($user);

        return $this->render('rh/dashboard/index.html.twig', [
            'dashboard' => $dashboardData,
        ]);
    }
}

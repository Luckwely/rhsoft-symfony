<?php
namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\DashboardService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    public function __construct(private DashboardService $dashboardService)
    {
    }

    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        if (!$user instanceof User) {
            throw $this->createAccessDeniedException();
        }

        if ($user->isFirstLogin()) {
            $user->setFirstLogin(false);
            $em->flush();
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'user' => $user,
            'dashboard' => $this->dashboardService->getAdminDashboardData($user),
        ]);
    }
}

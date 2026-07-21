<?php
namespace App\Controller\Admin;

use Doctrine\ORM\EntityManagerInterface; // <-- AJOUTER
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_admin_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        $user = $this->getUser();

        if ($user && $user->isFirstLogin()) {
            $user->setFirstLogin(false);
            $em->flush();
        }

        return $this->render('admin/dashboard/index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}

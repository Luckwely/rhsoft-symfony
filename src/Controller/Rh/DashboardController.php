<?php

namespace App\Controller\Rh; 

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DashboardController extends AbstractController
{
    #[Route('/dashboard', name: 'app_rh_dashboard')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('rh/dashboard/index.html.twig');
    }
}

<?php

namespace App\Controller\Rh; // 1. Changer le namespace

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')] // 2. Prefixer la route
final class DashboardController extends AbstractController // 3. Renommer la classe
{
    #[Route('/dashboard', name: 'app_rh_dashboard')]
    #[IsGranted('ROLE_RH')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('rh/dashboard/index.html.twig');
    }
}

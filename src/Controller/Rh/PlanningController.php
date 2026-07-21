<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class PlanningController extends AbstractController
{
    #[Route('/prompt', name: 'app_rh_planning')]
    public function index(): Response
    {
        return $this->render('rh/planning/index.html.twig', [
            'controller_name' => 'Rh/PlanningController',
        ]);
    }
}

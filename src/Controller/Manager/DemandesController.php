<?php

namespace App\Controller\Manager;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class DemandesController extends AbstractController
{
    #[Route('/demandes', name: 'app_manager_demandes')]
    public function index(): Response
    {
        return $this->render('manager/demandes/index.html.twig', [
            'controller_name' => 'Manager/DemandesController',
        ]);
    }
}

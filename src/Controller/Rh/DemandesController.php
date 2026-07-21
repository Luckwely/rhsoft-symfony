<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DemandesController extends AbstractController
{
    #[Route('/demandes', name: 'app_rh_demandes')]
    public function index(): Response
    {
        return $this->render('rh/demandes/index.html.twig', [
            'controller_name' => 'Rh/DemandesController',
        ]);
    }
}

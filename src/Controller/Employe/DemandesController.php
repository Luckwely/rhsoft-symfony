<?php

namespace App\Controller\Employe;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class DemandesController extends AbstractController
{
    #[Route('/demandes', name: 'app_employe_demandes')]
    public function index(): Response
    {
        return $this->render('employe/demandes/index.html.twig', [
            'controller_name' => 'Employe/DemandesController',
        ]);
    }
}

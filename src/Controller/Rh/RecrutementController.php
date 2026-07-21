<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class RecrutementController extends AbstractController
{
    #[Route('/rh/recrutement', name: 'app_rh_recrutement')]
    public function index(): Response
    {
        return $this->render('rh/recrutement/index.html.twig', [
            'controller_name' => 'Rh/RecrutementController',
        ]);
    }
}

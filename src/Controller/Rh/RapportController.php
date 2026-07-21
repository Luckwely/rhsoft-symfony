<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class RapportController extends AbstractController
{
    #[Route('/rapport', name: 'app_rh_rapport')]
    public function index(): Response
    {
        return $this->render('rh/rapport/index.html.twig', [
            'controller_name' => 'Rh/RapportController',
        ]);
    }
}

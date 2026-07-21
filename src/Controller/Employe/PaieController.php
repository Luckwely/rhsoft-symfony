<?php

namespace App\Controller\Employe;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class PaieController extends AbstractController
{
    #[Route('/paie', name: 'app_employe_paie')]
    public function index(): Response
    {
        return $this->render('employe/paie/index.html.twig', [
            'controller_name' => 'Employe/PaieController',
        ]);
    }
}

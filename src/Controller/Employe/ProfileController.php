<?php

namespace App\Controller\Employe;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class ProfileController extends AbstractController
{
    #[Route('/employe/profile', name: 'app_employe_profile')]
    public function index(): Response
    {
        return $this->render('employe/profile/index.html.twig', [
            'controller_name' => 'Employe/ProfileController',
        ]);
    }
}

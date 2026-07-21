<?php

namespace App\Controller\Manager;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_manager_profile')]
    public function index(): Response
    {
        return $this->render('manager/profile/index.html.twig', [
            'controller_name' => 'Manager/ProfileController',
        ]);
    }
}

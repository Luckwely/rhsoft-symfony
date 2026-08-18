<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_rh_profile')]
    public function index(): Response
    {
        return $this->render('rh/profile/index.html.twig');
    }
}

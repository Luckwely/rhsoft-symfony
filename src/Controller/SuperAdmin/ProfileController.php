<?php

namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_super_admin_profile')]
    public function index(): Response
    {
        return $this->render('super_admin/profile/index.html.twig');
    }
}

<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_admin_profile')]
    public function index(): Response
    {
        return $this->render('admin/profile/index.html.twig');
    }
}

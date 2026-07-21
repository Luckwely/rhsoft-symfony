<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class CongeController extends AbstractController
{
    #[Route('/conge', name: 'app_admin_conge')]
    public function index(): Response
    {
        return $this->render('admin/conge/index.html.twig', [
            'controller_name' => 'Admin/CongeController',
        ]);
    }
}

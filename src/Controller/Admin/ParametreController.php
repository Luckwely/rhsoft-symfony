<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ParametreController extends AbstractController
{
    #[Route('/parametre', name: 'app_admin_parametre')]
    public function index(): Response
    {
        return $this->render('admin/parametre/index.html.twig', [
            'controller_name' => 'Admin/ParametreController',
        ]);
    }
}

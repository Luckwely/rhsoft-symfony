<?php

namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ParametreController extends AbstractController
{
    #[Route('/parametre', name: 'app_super_admin_parametre')]
    public function index(): Response
    {
        return $this->render('super_admin/parametre/index.html.twig', [
            'controller_name' => 'SuperAdmin/ParametreController',
        ]);
    }
}

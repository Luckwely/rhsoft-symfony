<?php

namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class EntrepriseController extends AbstractController
{
    #[Route('/entreprise', name: 'app_super_admin_entreprise')]
    public function index(): Response
    {
        return $this->render('super_admin/entreprise/list.html.twig', [
            'controller_name' => 'SuperAdmin/EntrepriseController',
        ]);
    }
}

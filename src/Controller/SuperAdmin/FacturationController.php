<?php

namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class FacturationController extends AbstractController
{
    #[Route('/facturation', name: 'app_super_admin_facturation')]
    public function index(): Response
    {
        return $this->render('super_admin/facturation/abonnement.html.twig', [
            'controller_name' => 'SuperAdmin/FacturationController',
        ]);
    }
}

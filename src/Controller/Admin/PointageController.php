<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class PointageController extends AbstractController
{
    #[Route('/pointage', name: 'app_admin_pointage')]
    public function index(): Response
    {
        return $this->render('admin/pointage/index.html.twig', [
            'controller_name' => 'Admin/PointageController',
        ]);
    }
}

<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ReportingController extends AbstractController
{
    #[Route('/reporting', name: 'app_admin_reporting')]
    public function index(): Response
    {
        return $this->render('admin/reporting/index.html.twig', [
            'controller_name' => 'Admin/ReportingController',
        ]);
    }
}

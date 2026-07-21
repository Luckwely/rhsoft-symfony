<?php

namespace App\Controller\Admin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class PlanningController extends AbstractController
{
    #[Route('/admin/planning', name: 'app_employee_planning')]
    public function index(): Response
    {
        return $this->render('admin/planning/index.html.twig', [
            'controller_name' => 'Admin/PlanningController',
        ]);
    }
}

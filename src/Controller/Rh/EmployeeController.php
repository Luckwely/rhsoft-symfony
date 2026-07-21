<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class EmployeeController extends AbstractController
{
    #[Route('/employee', name: 'app_rh_employee')]
    public function index(): Response
    {
        return $this->render('rh/employee/index.html.twig', [
            'controller_name' => 'Rh/EmployeeController',
        ]);
    }
}

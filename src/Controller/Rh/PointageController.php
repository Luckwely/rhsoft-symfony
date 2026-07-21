<?php

namespace App\Controller\Rh;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class PointageController extends AbstractController
{
    #[Route('/pointage', name: 'app_rh_pointage')]
    public function index(): Response
    {
        return $this->render('rh/pointage/index.html.twig', [
            'controller_name' => 'Rh/PointageController',
        ]);
    }
}

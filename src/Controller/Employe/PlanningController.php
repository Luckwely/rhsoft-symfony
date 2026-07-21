<?php

namespace App\Controller\Employe;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class PlanningController extends AbstractController
{
    #[Route('/planning', name: 'app_employe_planning')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('employe/planning/index.html.twig');
    }
}

<?php

namespace App\Controller\Manager;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class MonEquipeController extends AbstractController
{
    #[Route('/monEquipe', name: 'app_manager_monEquipe')]
    public function index(EntityManagerInterface $em): Response
    {
        return $this->render('manager/monEquipe/index.html.twig');
    }
}

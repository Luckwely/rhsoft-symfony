<?php

namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class FonctionnaliteController extends AbstractController
{
    #[Route('/fonctionnalite', name: 'app_fonctionnalite')]
    public function index(): Response
    {
        return $this->render('public/fonctionnalite/index.html.twig', [
            'controller_name' => 'FonctionnaliteController',
        ]);
    }
}

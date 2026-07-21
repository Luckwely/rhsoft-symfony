<?php
namespace App\Controller\Public;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PricingController extends AbstractController
{
    #[Route('/pricing', name: 'app_pricing')]
    public function index(): Response
    {
        $plans = [
            'essai' => [
                'name' => 'Essai',
                'price' => '0',
                'period' => '/14 jours',
                'features' => ['Accès limité', '1 Entreprise'],
                'highlight' => false
            ],
            'premium' => [
                'name' => 'Premium',
                'price' => '9,99',
                'period' => '/mois',
                'features' => ['Tout de Essai', '5 Utilisateurs'],
                'highlight' => false
            ],
            'vip' => [
                'name' => 'VIP',
                'price' => '19,99',
                'period' => '/mois',
                'features' => ['Tout de Premium', 'Utilisateurs illimités'],
                'highlight' => false
            ],
        ];
        return $this->render('public/pricing/index.html.twig', [
            'plans' => $plans
        ]);
    }
}

<?php

namespace App\Controller\SuperAdmin;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;
use App\Repository\EntrepriseRepository;
use Symfony\Component\HttpFoundation\Request;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class FacturationController extends AbstractController
{
    #[Route('/facturation', name: 'app_super_admin_facturation')]
    public function index(Request $request, EntrepriseRepository $repo, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('q');
        $plan = $request->query->get('plan');
        $status = $request->query->get('status');

        $query = $repo->findAllWithAbonnementFilters($search, $plan, $status);
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 10);
        $stats = $repo->getAbonnementStats();

        return $this->render('super_admin/facturation/abonnement.html.twig', [
            'pagination' => $pagination,
            'stats' => $stats,
        ]);
    }
}

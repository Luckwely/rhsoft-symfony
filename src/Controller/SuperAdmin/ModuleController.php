<?php

namespace App\Controller\SuperAdmin;

use App\Entity\Entreprise;
use App\Repository\EntrepriseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
final class ModuleController extends AbstractController
{
    #[Route('/module', name: 'app_super_admin_module')]
    public function index(Request $request, EntrepriseRepository $repo, PaginatorInterface $paginator): Response
    {
        $search = $request->query->get('q');
        $module = $request->query->get('module');
        $status = $request->query->get('status');

        $query = $repo->findAllWithModuleFilters($search, $module, $status);
        $pagination = $paginator->paginate($query, $request->query->getInt('page', 1), 10);

        $stats = [
            'totalModules' => 3,
            'tauxActivation' => $repo->getTauxActivation(),
            'paie' => $repo->count(['modulePaie' => true]),
            'pointage' => $repo->count(['modulePointage' => true]),
        ];

        return $this->render('super_admin/module/index.html.twig', [
            'pagination' => $pagination,
            'stats' => $stats,
        ]);
    }

    #[Route('/entreprise/{id}/toggle-module', name: 'app_super_admin_toggle_module', methods: ['POST'])]
    public function toggleModule(Request $request, Entreprise $entreprise, EntityManagerInterface $em): JsonResponse
    {
        $module = $request->request->get('module'); // paie, pointage, rh
        $value = $request->request->get('value') === 'true';

        match($module) {
            'paie' => $entreprise->setModulePaie($value),
            'pointage' => $entreprise->setModulePointage($value),
            'rh' => $entreprise->setModuleRh($value),
        };

        $em->flush();
        return new JsonResponse(['success' => true]);
    }

}

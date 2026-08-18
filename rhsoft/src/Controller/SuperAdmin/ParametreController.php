<?php

namespace App\Controller\SuperAdmin;

use App\Repository\SystemLogRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
#[Route('/super/admin')]
#[IsGranted('ROLE_SUPER_ADMIN')]
class ParametreController extends AbstractController
{
    #[Route('/parametre', name: 'app_super_admin_parametre')]
    public function index(Request $request, SystemLogRepository $logRepository, PaginatorInterface $paginator): Response
    {
        // Filters for Connections
        $connStatus = $request->query->get('conn_status');

        // Filters for Errors
        $errSearch = $request->query->get('err_search');
        $errLevel = $request->query->get('err_level');

        // Queries
        $connQuery = $logRepository->findLogsByTypeQuery('connection', $connStatus);
        $errQuery = $logRepository->findLogsByTypeQuery('error', null, $errLevel, $errSearch);

        // Pagination
        $connections = $paginator->paginate(
            $connQuery,
            $request->query->getInt('conn_page', 1),
            6,
            ['pageParameterName' => 'conn_page']
        );

        $errors = $paginator->paginate(
            $errQuery,
            $request->query->getInt('err_page', 1),
            6,
            ['pageParameterName' => 'err_page']
        );

        return $this->render('super_admin/parametre/index.html.twig', [
            'connections' => $connections,
            'errors' => $errors,
            'latest_critical' => $logRepository->findLatestCriticalError(),
            'total_connections' => $logRepository->countByType('connection'),
            'total_errors' => $logRepository->countByType('error'),
        ]);
    }
}

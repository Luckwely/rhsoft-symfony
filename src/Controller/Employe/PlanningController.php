<?php
namespace App\Controller\Employe;

use App\Service\PlanningService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_EMPLOYE')]
final class PlanningController extends AbstractController
{
    public function __construct(private PlanningService $planningService) {}

    #[Route('/pointage', name: 'app_employe_planning')]
    public function index(): Response
    {
        $user = $this->getUser();
        if(!$user->getEntreprise()->isModulePointage()){
            $this->addFlash('danger', 'Module désactivé');
            return $this->redirectToRoute('app_employe_profile');
        }

        $data = array_merge(
            $this->planningService->getTodayData($user),
            $this->planningService->getWeekSummary($user)
        );

        return $this->render('employe/planning/index.html.twig', $data);
    }

    #[Route('/pointage/entree', name: 'app_employe_pointage_entree', methods: ['POST'])]
    public function pointerEntree(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($this->isCsrfTokenValid('pointer_entree', $request->request->get('_token'))) {
            $this->planningService->pointerEntree($this->getUser());
            $this->addFlash('success', 'Entrée pointée à '.(new \DateTime())->format('H:i'));
        }
        return $this->redirectToRoute('app_employe_planning');
    }

    #[Route('/pointage/sortie', name: 'app_employe_pointage_sortie', methods: ['POST'])]
    public function pointerSortie(Request $request): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');
        if ($this->isCsrfTokenValid('pointer_sortie', $request->request->get('_token'))) {
            $this->planningService->pointerSortie($this->getUser());
            $this->addFlash('success', 'Sortie pointée à '.(new \DateTime())->format('H:i'));
        }
        return $this->redirectToRoute('app_employe_planning');
    }

}

<?php

namespace App\Controller\Manager;

use App\Entity\Conge;
use App\Form\CongeType;
use App\Service\CongeManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Permet au manager de demander ses propres congés, comme n'importe quel employé.
 * Les demandes soumises ici passent ensuite par le circuit de validation RH/Admin habituel
 * (via app_rh_demandes_conge / app_admin_conge).
 */
#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class CongeController extends AbstractController
{
    #[Route('/mes-conges', name: 'app_manager_conge')]
    public function index(Request $request, CongeManagerService $congeManager): Response
    {
        $user = $this->getUser();
        $conge = new Conge();

        $canRequestLeave = $congeManager->canRequestLeave($user);

        $form = $this->createForm(CongeType::class, $conge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if (!$canRequestLeave) {
                $this->addFlash('error', sprintf(
                    'Vous devez avoir au moins 3 mois d\'ancienneté pour pouvoir demander un congé (ancienneté actuelle : %d mois).',
                    $congeManager->getMonthsOfService($user)
                ));

                return $this->redirectToRoute('app_manager_conge');
            }

            try {
                $congeManager->processLeaveRequest($conge, $user);
                $this->addFlash('success', 'Votre demande de congé a été soumise avec succès.');
            } catch (\RuntimeException $e) {
                // Message métier (ancienneté insuffisante, solde insuffisant...) affichable tel quel
                $this->addFlash('error', $e->getMessage());
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement.');
            }

            return $this->redirectToRoute('app_manager_conge');
        }

        $congesHistorique = $congeManager->getEmployeeHistory($user);
        $leaveData = $congeManager->getLeaveBalanceData($user);

        return $this->render('manager/conge/index.html.twig', array_merge([
            'form' => $form->createView(),
            'congesHistorique' => $congesHistorique,
            'canRequestLeave' => $canRequestLeave,
            'moisAnciennete' => $congeManager->getMonthsOfService($user),
        ], $leaveData));
    }
}

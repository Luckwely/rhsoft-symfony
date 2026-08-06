<?php

namespace App\Controller\Employe;

use App\Entity\Conge;
use App\Form\CongeType;
use App\Service\CongeManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class CongeController extends AbstractController
{
    #[Route('/conge', name: 'app_employe_conge')]
    public function index(Request $request, CongeManagerService $congeManager): Response
    {
        $user = $this->getUser();
        $conge = new Conge();

        $form = $this->createForm(CongeType::class, $conge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $congeManager->processLeaveRequest($conge, $user);
                $this->addFlash('success', 'Votre demande de congé a été soumise avec succès.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Une erreur est survenue lors de l\'enregistrement.');
            }

            return $this->redirectToRoute('app_employe_conge');
        }

        $congesHistorique = $congeManager->getEmployeeHistory($user);
        $leaveData = $congeManager->getLeaveBalanceData($user);

        return $this->render('employe/conge/index.html.twig', array_merge([
            'form' => $form->createView(),
            'congesHistorique' => $congesHistorique,
        ], $leaveData));
    }
}

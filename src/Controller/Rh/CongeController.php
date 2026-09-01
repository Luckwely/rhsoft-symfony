<?php

namespace App\Controller\Rh;

use App\Entity\Conge;
use App\Form\CongeType;
use App\Service\CongeManagerService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Permet au RH de demander ses propres congés, comme n'importe quel employé.
 * Les demandes soumises ici passent ensuite par le circuit de validation Admin habituel
 * (via app_admin_conge).
 */
#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class CongeController extends AbstractController
{
    #[Route('/mes-conges', name: 'app_rh_conge')]
    public function index(Request $request, CongeManagerService $congeManager, PaginatorInterface $paginator): Response
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

                return $this->redirectToRoute('app_rh_conge');
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

            return $this->redirectToRoute('app_rh_conge');
        }

        $congesHistorique = $paginator->paginate(
            $congeManager->getEmployeeHistory($user),
            $request->query->getInt('page', 1),
            10
        );
        $leaveData = $congeManager->getLeaveBalanceData($user);

        return $this->render('rh/conge/index.html.twig', array_merge([
            'form' => $form->createView(),
            'congesHistorique' => $congesHistorique,
            'canRequestLeave' => $canRequestLeave,
            'moisAnciennete' => $congeManager->getMonthsOfService($user),
        ], $leaveData));
    }

    #[Route('/mes-conges/export', name: 'app_rh_conge_export', methods: ['GET'])]
    public function export(CongeManagerService $congeManager): Response
    {
        $user = $this->getUser();
        $lines = ['Type;Date début;Date fin;Nombre de jours;Statut;Motif'];

        foreach ($congeManager->getEmployeeHistory($user) as $conge) {
            $values = [
                $conge->getTypeConge()?->getNom() ?? 'Autre',
                $conge->getDateDebut()?->format('d/m/Y') ?? '',
                $conge->getDateFin()?->format('d/m/Y') ?? '',
                (string) $conge->getNbJours(),
                $conge->getStatut(),
                $conge->getMotif() ?? '',
            ];
            $lines[] = implode(';', array_map(static fn($value): string => '"'.str_replace('"', '""', (string) $value).'"', $values));
        }

        return new Response("\xEF\xBB\xBF".implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mes-conges.csv"',
        ]);
    }
}

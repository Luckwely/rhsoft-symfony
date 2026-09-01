<?php

namespace App\Controller\Manager;

use App\Entity\AvanceSalaire;
use App\Form\AvanceSalaireType;
use App\Repository\AvanceSalaireRepository;
use App\Service\AvanceLimitService;
use App\Service\AvanceNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Form\FormError;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Permet au manager de demander sa propre avance sur salaire, comme n'importe quel employé.
 * Les demandes soumises ici passent ensuite par le circuit de validation RH habituel
 * (via app_rh_demandes_avance).
 */
#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class AvanceController extends AbstractController
{
    #[Route('/avance', name: 'app_manager_avance', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        AvanceSalaireRepository $avanceRepository,
        AvanceLimitService $avanceLimitService,
        AvanceNotificationService $avanceNotificationService,
        PaginatorInterface $paginator
    ): Response
    {
        $user = $this->getUser();

        $avance = new AvanceSalaire();
        $avance->setEmployee($user);
        $form = $this->createForm(AvanceSalaireType::class, $avance, ['employee' => $user]);
        $form->handleRequest($request);

        $demandeEnAttente = $form->isSubmitted() && $avanceRepository->hasDemandeEnAttente($user);
        if ($demandeEnAttente) {
            $form->addError(new FormError('Vous avez déjà une demande d\'avance en attente de traitement. Attendez sa validation avant d\'en soumettre une nouvelle.'));
        }

        if ($form->isSubmitted() && $form->isValid() && !$demandeEnAttente) {
            $montantDemande = (float) $avance->getMontant();
            $montantDisponibleActuel = $avanceLimitService->getMontantDisponible($user);
            if ($montantDemande > $montantDisponibleActuel + 0.01) {
                $form->addError(new FormError(sprintf(
                    'Le montant demandé dépasse le montant disponible actuel : %.2f Ar.',
                    $montantDisponibleActuel
                )));
            }

            if (!$form->isValid()) {
                $demandesPrecedentes = $paginator->paginate(
                    $avanceRepository->findBy(['employee' => $user], ['dateDemande' => 'DESC']),
                    $request->query->getInt('page', 1),
                    10
                );
                return $this->render('manager/avance/index.html.twig', [
                    'form' => $form->createView(),
                    'demandesPrecedentes' => $demandesPrecedentes,
                    'plafondPourcentage' => $avanceLimitService->getPlafondPourcentage($user),
                    'plafondMontant' => $avanceLimitService->getPlafondMontant($user),
                    'encours' => $avanceLimitService->getEncours($user),
                    'montantDisponible' => $montantDisponibleActuel,
                    'montantMinimum' => $avanceLimitService->getMontantMinimum(),
                ]);
            }

            $avance->setEmployee($user);
            $avance->setEntreprise($user->getEntreprise());
            $avance->setDateDemande(new \DateTimeImmutable());
            $avance->setStatut(AvanceSalaire::STATUS_DEMANDE);

            $entityManager->persist($avance);
            $entityManager->flush();

            $avanceNotificationService->notifierSoumission($avance);

            $this->addFlash('success', 'Votre demande d\'avance a été envoyée avec succès.');

            return $this->redirectToRoute('app_manager_avance');
        }

        $demandesPrecedentes = $paginator->paginate(
            $avanceRepository->findBy(['employee' => $user], ['dateDemande' => 'DESC']),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('manager/avance/index.html.twig', [
            'form' => $form->createView(),
            'demandesPrecedentes' => $demandesPrecedentes,
            'plafondPourcentage' => $avanceLimitService->getPlafondPourcentage($user),
            'plafondMontant' => $avanceLimitService->getPlafondMontant($user),
            'encours' => $avanceLimitService->getEncours($user),
            'montantDisponible' => $avanceLimitService->getMontantDisponible($user),
            'montantMinimum' => $avanceLimitService->getMontantMinimum(),
        ]);
    }
}

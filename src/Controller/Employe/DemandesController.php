<?php

namespace App\Controller\Employe;

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
use App\Entity\Demission;
use App\Form\DemissionType;
use App\Repository\DemissionRepository;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class DemandesController extends AbstractController
{
    #[Route('/avance', name: 'app_employe_demandes_avance', methods: ['GET', 'POST'])]
    public function avance(
        Request $request,
        EntityManagerInterface $entityManager,
        AvanceSalaireRepository $avanceRepository,
        AvanceLimitService $avanceLimitService,
        AvanceNotificationService $avanceNotificationService
    ): Response
    {
        $user = $this->getUser();

        $avance = new AvanceSalaire();
        $avance->setEmployee($this->getUser()); // Assign current employee
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
        }

        if ($form->isSubmitted() && $form->isValid() && !$demandeEnAttente) {
            $avance->setEmployee($user);
            $avance->setDateDemande(new \DateTimeImmutable());
            $avance->setEntreprise($user->getEntreprise()); // Assuming your User entity has getEntreprise()
            $avance->setStatut(AvanceSalaire::STATUS_DEMANDE);

            $entityManager->persist($avance);
            $entityManager->flush();

            $avanceNotificationService->notifierSoumission($avance);

            $this->addFlash('success', 'Votre demande d\'avance a été envoyée avec succès.');

            return $this->redirectToRoute('app_employe_demandes_avance');
        }

        // Fetch past requests for the logged-in user
        $demandesPrecedentes = $avanceRepository->findBy(['employee' => $user], ['dateDemande' => 'DESC']);

        return $this->render('employe/demandes/avance.html.twig', [
            'form' => $form->createView(),
            'demandesPrecedentes' => $demandesPrecedentes,
            // Plafond dynamique (% du salaire de base, encours déduit) à afficher près du
            // champ montant, pour remplacer l'ancien "maximum 800 Ar" fixe et identique à tous.
            'plafondPourcentage' => $avanceLimitService->getPlafondPourcentage($user),
            'plafondMontant' => $avanceLimitService->getPlafondMontant($user),
            'encours' => $avanceLimitService->getEncours($user),
            'montantDisponible' => $avanceLimitService->getMontantDisponible($user),
            'montantMinimum' => $avanceLimitService->getMontantMinimum(),
        ]);
    }

    #[Route('/demission', name: 'app_employe_demandes_demission', methods: ['GET', 'POST'])]
    public function demission(
        Request $request,
        EntityManagerInterface $entityManager,
        DemissionRepository $demissionRepository
    ): Response
    {
        $user = $this->getUser();

        $demission = new Demission();
        $form = $this->createForm(DemissionType::class, $demission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $demission->setEmployee($user);
            $demission->setEntreprise($user->getEntreprise());
            $demission->setDateDemande(new \DateTimeImmutable());
            $demission->setStatut('en_attente');

            $entityManager->persist($demission);
            $entityManager->flush();

            $this->addFlash('success', 'Votre lettre de démission a été envoyée avec succès.');

            return $this->redirectToRoute('app_employe_demandes_demission');
        }

        // Fetch history for the connected user
        $demissionsPrecedentes = $demissionRepository->findBy(['employee' => $user], ['dateDemande' => 'DESC']);

        return $this->render('employe/demandes/demission.html.twig', [
            'form' => $form->createView(),
            'demissionsPrecedentes' => $demissionsPrecedentes,
        ]);
    }
}

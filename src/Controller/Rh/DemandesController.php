<?php
namespace App\Controller\Rh;

use App\Entity\Conge;
use App\Entity\AvanceSalaire;
use App\Repository\CongeRepository;
use App\Repository\AvanceSalaireRepository;
use App\Service\AvanceLimitService;
use App\Service\AvanceNotificationService;
use App\Service\CongeManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DemandesController extends AbstractController
{
    #[Route('/conge', name: 'app_rh_demandes_conge')]
    public function conge(CongeRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $entreprise = $this->getUser()->getEntreprise();

        // Filtres
        $type = $request->query->get('type');
        $search = $request->query->get('search');

        // Un RH ne doit pas voir sa propre demande dans sa propre file de validation
        // (il ne peut de toute façon pas se l'auto-approuver, cf. valider()/refuser() ci-dessous).
        $demandes = $paginator->paginate(
            $repo->createEnAttenteByEntrepriseQueryBuilder($entreprise, $type, $search, $this->getUser()),
            $request->query->getInt('page', 1),
            10
        );
        $nbEnAttente = $demandes->getTotalItemCount();

        return $this->render('rh/demandes/conge.html.twig', [
            'demandes' => $demandes,
            'nbEnAttente' => $nbEnAttente,
            'currentType' => $type,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/conge/{id}/valider', name: 'app_rh_conge_valider', methods: ['POST'])]
    public function valider(Request $request, Conge $conge, EntityManagerInterface $em, CongeManagerService $congeManager): Response
    {
        if (!$this->isCsrfTokenValid('rh_conge_valider_'.$conge->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_demandes_conge');
        }
        $this->denyAccessUnlessGranted('RH_EDIT', $conge); // sécurité entreprise

        // Un utilisateur ne peut pas valider sa propre demande de congé, quelle que soit
        // la manière dont la requête est envoyée (l'UI ne fait que refléter cette règle).
        if ($conge->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas approuver votre propre demande de congé.');
        }

        try {
            // Re-validation à l'approbation : ancienneté/solde sont re-vérifiés, mais pas
            // la règle "date de début pas dans le passé" (cf. commentaire du service) —
            // sinon un simple délai de traitement rend la demande définitivement bloquée.
            $congeManager->validateLeaveRequestForApproval($conge, $conge->getEmployee());
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_rh_demandes_conge');
        }

        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        // Répercute automatiquement le congé sur le planning de l'employé (jours verrouillés)
        $congeManager->syncPlanningForValidatedConge($conge);

        $this->addFlash('success', 'Congé validé pour '.$conge->getEmployee()->getNom());
        return $this->redirectToRoute('app_rh_demandes_conge');
    }

    #[Route('/conge/{id}/refuser', name: 'app_rh_conge_refuser', methods: ['POST'])]
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('rh_conge_refuser_'.$conge->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_demandes_conge');
        }
        $this->denyAccessUnlessGranted('RH_EDIT', $conge);

        if ($conge->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter votre propre demande de congé.');
        }

        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setMotif($request->request->get('motif'));
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé');
        return $this->redirectToRoute('app_rh_demandes_conge');
    }



    #[Route('/avance', name: 'app_rh_demandes_avance')]
    public function avance(AvanceSalaireRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        // Un RH ne doit pas voir ses propres demandes d'avance dans sa propre file
        // d'approbation/paiement : cf. AvanceSalaireRepository pour le détail.
        $demandes = $paginator->paginate(
            $repo->createEnAttenteByEntrepriseQueryBuilder($entreprise),
            $request->query->getInt('page', 1),
            10,
            ['pageParameterName' => 'page_demandes']
        );
        // Avances déjà approuvées mais pas encore effectivement versées à l'employé :
        // file d'attente de paiement, distincte de la file d'approbation.
        $aVerser = $paginator->paginate(
            $repo->createAVerserByEntrepriseQueryBuilder($entreprise),
            $request->query->getInt('page_paiement', 1),
            10,
            ['pageParameterName' => 'page_paiement']
        );
        $stats = $repo->getStatsMois($entreprise);

        return $this->render('rh/demandes/avance.html.twig', [
            'demandes' => $demandes,
            'aVerser' => $aVerser,
            'stats' => $stats,
            'modesPaiement' => AvanceSalaire::MODES_PAIEMENT,
        ]);
    }

    #[Route('/avance/{id}/approuver', name: 'app_rh_avance_approuver', methods: ['POST'])]
    public function approuverAvance(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceLimitService $avanceLimitService, AvanceNotificationService $avanceNotificationService): Response
    {
        if (!$this->isCsrfTokenValid('rh_avance_approuver_'.$avance->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }
        if ($avance->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cette demande n'appartient pas à votre entreprise.");
        }

        // Un utilisateur ne peut pas valider sa propre demande d'avance, quelle que soit
        // la manière dont la requête est envoyée (l'UI ne fait que refléter cette règle).
        if ($avance->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas approuver votre propre demande d\'avance.');
        }

        // Empêche une double approbation/traitement concurrent (ex: deux clics, deux RH).
        if ($avance->getStatut() !== AvanceSalaire::STATUS_DEMANDE) {
            $this->addFlash('info', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }

        // Re-contrôle serveur du plafond au moment de l'approbation (et pas seulement à la
        // soumission), pour éviter qu'un cumul de petites demandes dépasse le plafond autorisé.
        if ($avanceLimitService->depassePlafond($avance->getEmployee())) {
            $this->addFlash('danger', sprintf(
                'Impossible d\'approuver : le cumul des avances en cours de %s %s (%.2f) dépasserait le plafond autorisé (%.2f, soit %d%% du salaire de base).',
                $avance->getEmployee()->getPrenom(),
                $avance->getEmployee()->getNom(),
                $avanceLimitService->getEncours($avance->getEmployee()),
                $avanceLimitService->getPlafondMontant($avance->getEmployee()),
                $avanceLimitService->getPlafondPourcentage($avance->getEmployee())
            ));
            return $this->redirectToRoute('app_rh_demandes_avance');
        }

        $avance->setStatut(AvanceSalaire::STATUS_VALIDE);
        $avance->setValidePar($this->getUser());
        $em->flush();
        $avanceNotificationService->notifierApprobation($avance);
        $this->addFlash('success', 'Avance approuvée. Il reste à en déclencher le versement effectif.');
        return $this->redirectToRoute('app_rh_demandes_avance');
    }

    #[Route('/avance/{id}/rejeter', name: 'app_rh_avance_rejeter', methods: ['POST'])]
    public function rejeterAvance(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceNotificationService $avanceNotificationService): Response
    {
        if (!$this->isCsrfTokenValid('rh_avance_rejeter_'.$avance->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }
        if ($avance->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cette demande n'appartient pas à votre entreprise.");
        }

        if ($avance->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter votre propre demande d\'avance.');
        }

        if ($avance->getStatut() !== AvanceSalaire::STATUS_DEMANDE) {
            $this->addFlash('info', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }

        $avance->setStatut(AvanceSalaire::STATUS_REFUSE);
        $avance->setMotif($request->request->get('motif', $avance->getMotif()));
        $avance->setValidePar($this->getUser());
        $em->flush();
        $avanceNotificationService->notifierRejet($avance);
        $this->addFlash('danger', 'Avance rejetée');
        return $this->redirectToRoute('app_rh_demandes_avance');
    }

    /**
     * Déclenche le versement effectif d'une avance déjà approuvée : jusqu'ici, "approuver"
     * ne faisait que changer un statut sans qu'aucun argent ne soit réellement transféré.
     * Cette étape enregistre le mode de paiement et la référence de transfert (virement,
     * espèces, mobile money...), afin de tracer le versement réel à l'employé.
     */
    #[Route('/avance/{id}/marquer-paye', name: 'app_rh_avance_marquer_paye', methods: ['POST'])]
    public function marquerPayeeAvance(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceNotificationService $avanceNotificationService): Response
    {
        if (!$this->isCsrfTokenValid('rh_avance_payer_'.$avance->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }
        if ($avance->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cette demande n'appartient pas à votre entreprise.");
        }

        if ($avance->getStatut() !== AvanceSalaire::STATUS_VALIDE) {
            $this->addFlash('info', 'Cette avance doit d\'abord être approuvée avant de pouvoir être marquée comme payée.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }

        $modePaiement = $request->request->get('mode_paiement');
        if (!in_array($modePaiement, AvanceSalaire::MODES_PAIEMENT, true)) {
            $this->addFlash('danger', 'Merci de sélectionner un mode de paiement valide.');
            return $this->redirectToRoute('app_rh_demandes_avance');
        }

        $avance->setStatut(AvanceSalaire::STATUS_PAYEE);
        $avance->setModePaiement($modePaiement);
        $avance->setReferencePaiement($request->request->get('reference_paiement') ?: null);
        $avance->setDatePaiement(new \DateTimeImmutable());
        $avance->setPayePar($this->getUser());
        $em->flush();

        $avanceNotificationService->notifierPaiement($avance);

        $this->addFlash('success', sprintf(
            'Avance de %.2f marquée comme versée à %s %s. Elle sera déduite de son prochain salaire net.',
            $avance->getMontant(),
            $avance->getEmployee()->getPrenom(),
            $avance->getEmployee()->getNom()
        ));

        return $this->redirectToRoute('app_rh_demandes_avance');
    }
}

<?php

namespace App\Controller\Admin;

use App\Entity\AvanceSalaire;
use App\Entity\Entreprise;
use App\Repository\AvanceSalaireRepository;
use App\Service\AvanceLimitService;
use App\Service\AvanceNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Approbation des avances sur salaire, côté Admin. Seul le RH approuve les avances au
 * quotidien (Rh/DemandesController), mais quand la demande émane du RH lui-même, il ne
 * peut pas se l'auto-approuver et il n'existe qu'un seul RH par entreprise : sans ce
 * contrôleur, sa demande resterait bloquée indéfiniment, faute de tout autre validateur.
 * L'Admin (propriétaire unique de l'entreprise) sert donc de filet de sécurité, ici comme
 * pour les congés. Le SuperAdmin (propriétaire de la plateforme) n'intervient jamais dans
 * la gestion RH d'une entreprise cliente : ce n'est pas de son ressort.
 */
#[Route('/admin/avance')]
#[IsGranted('ROLE_ADMIN')]
final class AvanceController extends AbstractController
{
    #[Route('/', name: 'app_admin_demandes_avance')]
    public function index(AvanceSalaireRepository $repo, PaginatorInterface $paginator, Request $request): Response
    {
        $entreprise = $this->getEntreprise();

        // Contrairement à la file RH (qui s'exclut lui-même), l'Admin voit toutes les
        // demandes en attente de l'entreprise : c'est le filet de sécurité générique.
        $demandes = $paginator->paginate($repo->createEnAttenteByEntrepriseQueryBuilder($entreprise), $request->query->getInt('page_demandes', 1), 10, ['pageParameterName' => 'page_demandes']);
        $aVerser = $paginator->paginate($repo->createAVerserByEntrepriseQueryBuilder($entreprise), $request->query->getInt('page_paiement', 1), 10, ['pageParameterName' => 'page_paiement']);
        $stats = $repo->getStatsMois($entreprise);

        return $this->render('admin/demande/avance/index.html.twig', [
            'demandes' => $demandes,
            'aVerser' => $aVerser,
            'stats' => $stats,
            'modesPaiement' => AvanceSalaire::MODES_PAIEMENT,
        ]);
    }

    #[Route('/{id}/approuver', name: 'app_admin_avance_approuver', methods: ['POST'])]
    public function approuver(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceLimitService $avanceLimitService, AvanceNotificationService $avanceNotificationService): Response
    {
        $this->denyAccessUnlessSameEntreprise($avance->getEntreprise());
        $this->validateCsrf('admin_avance_approuver_'.$avance->getId(), $request);

        if ($avance->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas approuver votre propre demande d\'avance.');
        }

        if ($avance->getStatut() !== AvanceSalaire::STATUS_DEMANDE) {
            $this->addFlash('info', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_admin_demandes_avance');
        }

        if ($avanceLimitService->depassePlafond($avance->getEmployee())) {
            $this->addFlash('danger', sprintf(
                'Impossible d\'approuver : le cumul des avances en cours de %s %s (%.2f) dépasserait le plafond autorisé (%.2f, soit %d%% du salaire de base).',
                $avance->getEmployee()->getPrenom(),
                $avance->getEmployee()->getNom(),
                $avanceLimitService->getEncours($avance->getEmployee()),
                $avanceLimitService->getPlafondMontant($avance->getEmployee()),
                $avanceLimitService->getPlafondPourcentage($avance->getEmployee())
            ));
            return $this->redirectToRoute('app_admin_demandes_avance');
        }

        $avance->setStatut(AvanceSalaire::STATUS_VALIDE);
        $avance->setValidePar($this->getUser());
        $em->flush();
        $avanceNotificationService->notifierApprobation($avance);
        $this->addFlash('success', 'Avance approuvée. Il reste à en déclencher le versement effectif.');
        return $this->redirectToRoute('app_admin_demandes_avance');
    }

    #[Route('/{id}/rejeter', name: 'app_admin_avance_rejeter', methods: ['POST'])]
    public function rejeter(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceNotificationService $avanceNotificationService): Response
    {
        $this->denyAccessUnlessSameEntreprise($avance->getEntreprise());
        $this->validateCsrf('admin_avance_rejeter_'.$avance->getId(), $request);

        if ($avance->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter votre propre demande d\'avance.');
        }

        if ($avance->getStatut() !== AvanceSalaire::STATUS_DEMANDE) {
            $this->addFlash('info', 'Cette demande a déjà été traitée.');
            return $this->redirectToRoute('app_admin_demandes_avance');
        }

        $avance->setStatut(AvanceSalaire::STATUS_REFUSE);
        $avance->setMotif($request->request->get('motif', $avance->getMotif()));
        $avance->setValidePar($this->getUser());
        $em->flush();
        $avanceNotificationService->notifierRejet($avance);
        $this->addFlash('danger', 'Avance rejetée');
        return $this->redirectToRoute('app_admin_demandes_avance');
    }

    #[Route('/{id}/marquer-paye', name: 'app_admin_avance_marquer_paye', methods: ['POST'])]
    public function marquerPayee(Request $request, AvanceSalaire $avance, EntityManagerInterface $em, AvanceNotificationService $avanceNotificationService): Response
    {
        $this->denyAccessUnlessSameEntreprise($avance->getEntreprise());
        $this->validateCsrf('admin_avance_payer_'.$avance->getId(), $request);

        if ($avance->getStatut() !== AvanceSalaire::STATUS_VALIDE) {
            $this->addFlash('info', 'Cette avance doit d\'abord être approuvée avant de pouvoir être marquée comme payée.');
            return $this->redirectToRoute('app_admin_demandes_avance');
        }

        $modePaiement = $request->request->get('mode_paiement');
        if (!in_array($modePaiement, AvanceSalaire::MODES_PAIEMENT, true)) {
            $this->addFlash('danger', 'Merci de sélectionner un mode de paiement valide.');
            return $this->redirectToRoute('app_admin_demandes_avance');
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

        return $this->redirectToRoute('app_admin_demandes_avance');
    }

    private function getEntreprise(): Entreprise
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getEntreprise') || !$user->getEntreprise()) {
            throw $this->createAccessDeniedException('Aucune entreprise associée à votre compte.');
        }

        return $user->getEntreprise();
    }

    private function validateCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }

    private function denyAccessUnlessSameEntreprise(?Entreprise $entreprise): void
    {
        if (!$entreprise || $entreprise !== $this->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }
    }

}

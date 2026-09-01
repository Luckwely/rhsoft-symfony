<?php
namespace App\Controller\Manager;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use App\Service\CongeManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class DemandesController extends AbstractController
{
    #[Route('/conge', name: 'app_manager_demandes_conge')]
    public function conge(CongeRepository $repo, Request $request, PaginatorInterface $paginator): Response
    {
        $entreprise = $this->getUser()->getEntreprise();

        // Filtres
        $type = $request->query->get('type');
        $search = $request->query->get('search');

        // Un manager ne doit pas voir sa propre demande dans sa propre file de validation
        // (il ne peut de toute façon pas se l'auto-approuver, cf. valider()/refuser() ci-dessous).
        $query = $repo->findEnAttenteByEntreprise($entreprise, $type, $search, $this->getUser());
        $demandes = $paginator->paginate($query, $request->query->getInt('page', 1), 10);
        $nbEnAttente = $repo->count(['entreprise' => $entreprise, 'statut' => Conge::STATUS_DEMANDE]);

        return $this->render('manager/demandes/conge.html.twig', [
            'demandes' => $demandes,
            'nbEnAttente' => $nbEnAttente,
            'currentType' => $type,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/conge/{id}/valider', name: 'app_manager_conge_valider', methods: ['POST'])]
    public function valider(Request $request, Conge $conge, EntityManagerInterface $em, CongeManagerService $congeManager): Response
    {
        if (!$this->isCsrfTokenValid('manager_conge_valider_'.$conge->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->denyAccessUnlessGranted('RH_EDIT', $conge); // sécurité entreprise

        // Un utilisateur ne peut pas valider sa propre demande de congé, quelle que soit
        // la manière dont la requête est envoyée (l'UI ne fait que refléter cette règle).
        if ($conge->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas approuver votre propre demande de congé.');
        }

        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        // Répercute automatiquement le congé sur le planning de l'employé (jours verrouillés)
        $congeManager->syncPlanningForValidatedConge($conge);

        $this->addFlash('success', 'Congé validé pour '.$conge->getEmployee()->getNom());
        return $this->redirectToRoute('app_manager_demandes_conge');
    }

    #[Route('/conge/{id}/refuser', name: 'app_manager_conge_refuser', methods: ['POST'])]
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('manager_conge_refuser_'.$conge->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
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
        return $this->redirectToRoute('app_manager_demandes_conge');
    }

}

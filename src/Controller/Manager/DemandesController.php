<?php
namespace App\Controller\Manager;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use Doctrine\ORM\EntityManagerInterface;
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
    public function conge(CongeRepository $repo, Request $request): Response
    {
        $entreprise = $this->getUser()->getEntreprise();

        // Filtres
        $type = $request->query->get('type');
        $search = $request->query->get('search');

        $demandes = $repo->findEnAttenteByEntreprise($entreprise, $type, $search);
        $nbEnAttente = $repo->count(['entreprise' => $entreprise, 'statut' => Conge::STATUS_DEMANDE]);

        return $this->render('manager/demandes/conge.html.twig', [
            'demandes' => $demandes,
            'nbEnAttente' => $nbEnAttente,
            'currentType' => $type,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/conge/{id}/valider', name: 'app_manager_conge_valider', methods: ['POST'])]
    public function valider(Conge $conge, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('RH_EDIT', $conge); // sécurité entreprise
        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Congé validé pour '.$conge->getEmployee()->getNom());
        return $this->redirectToRoute('app_manager_demandes_conge');
    }

    #[Route('/conge/{id}/refuser', name: 'app_manager_conge_refuser', methods: ['POST'])]
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('RH_EDIT', $conge);
        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setMotif($request->request->get('motif'));
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé');
        return $this->redirectToRoute('app_manager_demandes_conge');
    }

}

<?php
namespace App\Controller\Rh;

use App\Entity\Conge;
use App\Entity\AvanceSalaire;
use App\Repository\CongeRepository;
use App\Repository\AvanceSalaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DemandesController extends AbstractController
{
    #[Route('/conge', name: 'app_rh_demandes_conge')]
    public function conge(CongeRepository $repo, Request $request): Response
    {
        $entreprise = $this->getUser()->getEntreprise();

        // Filtres
        $type = $request->query->get('type');
        $search = $request->query->get('search');

        $demandes = $repo->findEnAttenteByEntreprise($entreprise, $type, $search);
        $nbEnAttente = $repo->count(['entreprise' => $entreprise, 'statut' => Conge::STATUS_DEMANDE]);

        return $this->render('rh/demandes/conge.html.twig', [
            'demandes' => $demandes,
            'nbEnAttente' => $nbEnAttente,
            'currentType' => $type,
            'currentSearch' => $search,
        ]);
    }

    #[Route('/conge/{id}/valider', name: 'app_rh_conge_valider', methods: ['POST'])]
    public function valider(Conge $conge, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('RH_EDIT', $conge); // sécurité entreprise
        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Congé validé pour '.$conge->getEmployee()->getNom());
        return $this->redirectToRoute('app_rh_demandes_conge');
    }

    #[Route('/conge/{id}/refuser', name: 'app_rh_conge_refuser', methods: ['POST'])]
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('RH_EDIT', $conge);
        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setMotif($request->request->get('motif'));
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé');
        return $this->redirectToRoute('app_rh_demandes_conge');
    }



    #[Route('/avance', name: 'app_rh_demandes_avance')]
    public function avance(AvanceSalaireRepository $repo): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $demandes = $repo->findEnAttenteByEntreprise($entreprise);
        $stats = $repo->getStatsMois($entreprise);

        return $this->render('rh/demandes/avance.html.twig', [
            'demandes' => $demandes,
            'stats' => $stats,
        ]);
    }

    #[Route('/avance/{id}/approuver', name: 'app_rh_avance_approuver', methods: ['POST'])]
    public function approuverAvance(AvanceSalaire $avance, EntityManagerInterface $em): Response
    {
        if ($avance->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cette demande n'appartient pas à votre entreprise.");
        }

        $avance->setStatut(AvanceSalaire::STATUS_VALIDE);
        $avance->setValidePar($this->getUser());
        $em->flush();
        $this->addFlash('success', 'Avance approuvée');
        return $this->redirectToRoute('app_rh_demandes_avance');
    }

    #[Route('/avance/{id}/rejeter', name: 'app_rh_avance_rejeter', methods: ['POST'])]
    public function rejeterAvance(Request $request, AvanceSalaire $avance, EntityManagerInterface $em): Response
    {
        if ($avance->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cette demande n'appartient pas à votre entreprise.");
        }

        $avance->setStatut(AvanceSalaire::STATUS_REFUSE);
        $avance->setMotif($request->request->get('motif', $avance->getMotif()));
        $em->flush();
        $this->addFlash('danger', 'Avance rejetée');
        return $this->redirectToRoute('app_rh_demandes_avance');
    }
}

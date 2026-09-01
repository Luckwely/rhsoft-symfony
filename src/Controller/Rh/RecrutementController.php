<?php namespace App\Controller\Rh;

use App\Entity\Offre;
use App\Form\OffreType;
use App\Entity\Candidature;
use App\Repository\OffreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Bundle\SecurityBundle\Security;

#[Route('/rh/offres')]
class RecrutementController extends AbstractController
{
    #[Route('/', name: 'app_rh_offre')]
    public function index(OffreRepository $offreRepository, Security $security): Response
    {
        $entreprise = $security->getUser()->getEntreprise();
        return $this->render('rh/recrutement/index.html.twig', [
            'offres' => $offreRepository->findBy(['entreprise' => $entreprise], ['id' => 'DESC']),
        ]);
    }

    #[Route('/new', name: 'app_rh_offre_new')]
    #[Route('/{id}/edit', name: 'app_rh_offre_edit')]
    public function form(Request $request, EntityManagerInterface $em, Offre $offre = null, Security $security): Response
    {
        $offre = $offre ?? new Offre();
        if (!$offre->getId()) {
            $offre->setEntreprise($security->getUser()->getEntreprise());
        }
        if ($offre->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas éditer cette offre.');
        }
        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offre);
            $em->flush();
            return $this->redirectToRoute('app_rh_offre');
        }
        return $this->render('rh/recrutement/form.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/candidatures', name: 'app_rh_offre_candidatures')]
    public function candidatures(Offre $offre, Security $security): Response
    {
        if ($offre->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        // FILTRER: uniquement les candidatures en attente
        $candidaturesEnAttente = $offre->getCandidatures()->filter(
            fn(Candidature $c) => $c->getStatut() === 'en_attente'
        );

        return $this->render('rh/recrutement/candidatures.html.twig', [
            'offre' => $offre,
            'candidatures' => $candidaturesEnAttente,
        ]);
    }

    #[Route('/candidature/{id}/accepter', name: 'rh_candidature_accepter')]
    public function accepterCandidature(Candidature $candidature, EntityManagerInterface $em, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        $candidature->setStatut('acceptee'); // <- MARQUE COMME ACCEPTEE
        $em->flush(); // <- IMPORTANT: flush avant redirection

        return $this->redirectToRoute('app_rh_employee_embauche', [
            'nom' => $candidature->getNom(),
            'prenom' => $candidature->getPrenom(),
            'email' => $candidature->getEmail(),
            'telephone' => $candidature->getTelephone(),
        ]);
    }

    #[Route('/candidature/{id}/refuser', name: 'rh_candidature_refuser')]
    public function refuserCandidature(Candidature $candidature, EntityManagerInterface $em, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        $candidature->setStatut('refusee'); // <- AU LIEU DE SUPPRIMER
        $em->flush();

        $this->addFlash('success', 'La candidature a été refusée.');
        return $this->redirectToRoute('app_rh_offre_candidatures', ['id' => $candidature->getOffre()->getId()]);
    }
}

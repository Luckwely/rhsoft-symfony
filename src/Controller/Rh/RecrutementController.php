<?php
namespace App\Controller\Rh;

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
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
class RecrutementController extends AbstractController
{
    // 1. LISTE : SEULEMENT LES OFFRES DE SON ENTREPRISE
    #[Route('/offres', name: 'app_rh_recrutement')]
    public function index(OffreRepository $offreRepository, Security $security): Response
    {
        $entreprise = $security->getUser()->getEntreprise();
        return $this->render('rh/recrutement/index.html.twig', [
            'offres' => $offreRepository->findBy(['entreprise' => $entreprise], ['id' => 'DESC']),
        ]);
    }

    // 2. CREER + EDIT
    #[Route('/offres/new', name: 'app_rh_offre_new')]
    #[Route('/offres/{id}/edit', name: 'app_rh_offre_edit')]
    public function form(Request $request, EntityManagerInterface $em, Offre $offre = null, Security $security): Response
    {
        $offre = $offre ?? new Offre();

        if (!$offre->getId()) {
            $offre->setEntreprise($security->getUser()->getEntreprise());
        }

        // SECURITE
        if ($offre->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas éditer cette offre.');
        }

        $form = $this->createForm(OffreType::class, $offre);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($offre);
            $em->flush();
            return $this->redirectToRoute('app_rh_recrutement');
        }

        return $this->render('rh/recrutement/form.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }

    #[Route('/offres/{id}/candidatures', name: 'app_rh_offre_candidatures')]
    public function candidatures(Offre $offre, Security $security): Response
    {
        // Security check to make sure the offer belongs to the logged-in admin's company
        if ($offre->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        return $this->render('rh/recrutement/candidatures.html.twig', [
            'offre' => $offre,
            'candidatures' => $offre->getCandidatures(),
        ]);
    }
    #[Route('/offres/candidature/{id}/accepter', name: 'rh_candidature_accepter')]
    public function accepterCandidature(Candidature $candidature, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirectToRoute('app_rh_employee_embauche', [
            'nom' => $candidature->getNom(),
            'email' => $candidature->getEmail(),
            'telephone' => $candidature->getTelephone(),
        ]);
    }

    #[Route('/offres/candidature/{id}/refuser', name: 'rh_candidature_refuser')]
    public function refuserCandidature(Candidature $candidature, EntityManagerInterface $em, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        $offreId = $candidature->getOffre()->getId();

        $em->remove($candidature);
        $em->flush();

        $this->addFlash('success', 'La candidature a été refusée et supprimée.');
        return $this->redirectToRoute('app_rh_offre_candidatures', ['id' => $offreId]);
    }
}

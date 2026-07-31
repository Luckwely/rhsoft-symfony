<?php
namespace App\Controller\Admin;

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

#[Route('/admin/offres')]
class AdminOffreController extends AbstractController
{
    // 1. LISTE : SEULEMENT LES OFFRES DE SON ENTREPRISE
    #[Route('/', name: 'app_admin_offre')]
    public function index(OffreRepository $offreRepository, Security $security): Response
    {
        $entreprise = $security->getUser()->getEntreprise();
        return $this->render('admin/admin_offre/index.html.twig', [
            'offres' => $offreRepository->findBy(['entreprise' => $entreprise], ['id' => 'DESC']),
        ]);
    }

    // 2. CREER + EDIT
    #[Route('/new', name: 'app_admin_offre_new')]
    #[Route('/{id}/edit', name: 'app_admin_offre_edit')]
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
            return $this->redirectToRoute('app_admin_offre');
        }

        return $this->render('admin/admin_offre/form.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }

    #[Route('/{id}/candidatures', name: 'app_admin_offre_candidatures')]
    public function candidatures(Offre $offre, Security $security): Response
    {
        // Security check to make sure the offer belongs to the logged-in admin's company
        if ($offre->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Accès refusé.');
        }

        return $this->render('admin/admin_offre/candidatures.html.twig', [
            'offre' => $offre,
            'candidatures' => $offre->getCandidatures(),
        ]);
    }
    #[Route('/candidature/{id}/accepter', name: 'admin_candidature_accepter')]
    public function accepterCandidature(Candidature $candidature, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        return $this->redirectToRoute('app_admin_employee_new', [
            'nom' => $candidature->getNom(),
            'email' => $candidature->getEmail(),
            'telephone' => $candidature->getTelephone(),
        ]);
    }

    #[Route('/candidature/{id}/refuser', name: 'admin_candidature_refuser')]
    public function refuserCandidature(Candidature $candidature, EntityManagerInterface $em, Security $security): Response
    {
        if ($candidature->getOffre()->getEntreprise() !== $security->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException();
        }

        $offreId = $candidature->getOffre()->getId();

        $em->remove($candidature);
        $em->flush();

        $this->addFlash('success', 'La candidature a été refusée et supprimée.');
        return $this->redirectToRoute('app_admin_offre_candidatures', ['id' => $offreId]);
    }
}

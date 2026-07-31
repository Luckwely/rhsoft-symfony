<?php
namespace App\Controller\Public;

use App\Entity\Offre;
use App\Entity\Candidature;
use App\Form\CandidatureType;// <-- IL MANQUAIT CE USE
use App\Repository\OffreRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/carriere')]
class OffreController extends AbstractController
{
    // 1. PAGE GLOBALE : TOUTES LES OFFRES
    #[Route('/', name: 'app_carriere_globale')]
    public function index(OffreRepository $offreRepository): Response
    {
        $offres = $offreRepository->findBy(
            ['status' => 'ouverte'],
            ['id' => 'DESC']
        );
        return $this->render('public/offre/index.html.twig', [
            'offres' => $offres,
            'titre' => 'Toutes les offres d\'emploi'
        ]);
    }

    // 2. PAGE PAR ENTREPRISE
    #[Route('/{slug}', name: 'app_carriere_entreprise')]
    public function byEntreprise(string $slug, OffreRepository $offreRepository): Response
    {
        $offres = $offreRepository->findByEntrepriseSlug($slug);
        return $this->render('public/offre/index.html.twig', [
            'offres' => $offres,
            'titre' => 'Offres de ' . $slug
        ]);
    }

    // 3. DETAIL PUBLIQUE - CORRIGE ICI
    #[Route('/offre/{id}', name: 'app_offre_show')]
    public function show(Offre $offre): Response
    {
        if ($offre->getStatus() !== 'ouverte') {
            throw $this->createNotFoundException();
        }

        return $this->render('public/offre/show.html.twig', [ // <-- CORRIGE LE CHEMIN ICI
            'offre' => $offre,
        ]);
    }

    #[Route('/offre/{id}/postuler', name: 'app_candidature_new')]
    public function candidature(Request $request, Offre $offre, EntityManagerInterface $em): Response
    {
        if ($offre->getStatus() !== 'ouverte') {
            throw $this->createNotFoundException();
        }

        $candidature = new Candidature();
        $candidature->setOffre($offre);
        $form = $this->createForm(CandidatureType::class, $candidature);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Upload CV
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = uniqid().'_'.preg_replace('/\s+/', '_', $cvFile->getClientOriginalName());
                try {
                    $cvFile->move($this->getParameter('cv_directory'), $newFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur upload CV');
                }
                $candidature->setCv($newFilename);
            }

            $em->persist($candidature);
            $em->flush();

            $this->addFlash('success', 'Votre candidature a bien été envoyée !');
            return $this->redirectToRoute('app_offre_show', ['id' => $offre->getId()]);
        }

        return $this->render('public/offre/candidature.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }

}

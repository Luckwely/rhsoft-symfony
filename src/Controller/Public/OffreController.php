<?php
namespace App\Controller\Public;

use App\Entity\Offre;
use App\Entity\Candidature;
use App\Form\CandidatureType;
use App\Repository\OffreRepository;
use App\Repository\EntrepriseRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

#[Route('/carriere')]
class OffreController extends AbstractController
{
    #[Route('/', name: 'app_carriere_globale')]
    public function index(Request $request, OffreRepository $offreRepository, PaginatorInterface $paginator): Response
    {
        $offres = $paginator->paginate(
            $offreRepository->createOpenOffersQuery(),
            $request->query->getInt('page', 1),
            9
        );
        return $this->render('public/offre/index.html.twig', [
            'offres' => $offres,
            'pagination' => $offres,
            'titre' => 'Toutes les offres d\'emploi'
        ]);
    }

    #[Route('/{slug}', name: 'app_carriere_entreprise')]
    public function byEntreprise(string $slug, Request $request, OffreRepository $offreRepository, EntrepriseRepository $entrepriseRepository, PaginatorInterface $paginator): Response
    {
        $entreprise = $entrepriseRepository->findOneBy(['slug' => $slug]);

        if (!$entreprise) {
            throw $this->createNotFoundException('Entreprise introuvable.');
        }

        $offres = $paginator->paginate(
            $offreRepository->createOpenOffersByEntrepriseSlugQuery($slug),
            $request->query->getInt('page', 1),
            9
        );

        return $this->render('public/offre/index.html.twig', [
            'offres' => $offres,
            'pagination' => $offres,
            'entreprise' => $entreprise,
            'titre' => 'Offres de ' . $entreprise->getNom(),
        ]);
    }

    #[Route('/offre/{id}', name: 'app_offre_show')]
    public function show(Offre $offre): Response
    {
        if ($offre->getStatus() !== 'ouverte') {
            throw $this->createNotFoundException();
        }

        return $this->render('public/offre/show.html.twig', [
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

            $lettreFile = $form->get('lettreMotivation')->getData();
            if ($lettreFile) {
                $newLettreFilename = uniqid().'_'.preg_replace('/\s+/', '_', $lettreFile->getClientOriginalName());
                try {
                    $lettreFile->move($this->getParameter('lettre_motivation_directory'), $newLettreFilename);
                    $candidature->setLettreMotivation($newLettreFilename);
                } catch (FileException $e) {
                    $this->addFlash('danger', 'Erreur upload lettre de motivation');
                }
            }

            $em->persist($candidature);
            $em->flush();

            $this->addFlash('success', 'Votre candidature a bien été envoyée !');
            return $this->redirectToRoute('app_candidature_new', ['id' => $offre->getId()]);
        }

        return $this->render('public/offre/candidature.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }
}

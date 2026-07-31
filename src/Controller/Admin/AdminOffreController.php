<?php
namespace App\Controller\Admin;

use App\Entity\Offre;
use App\Form\OffreType;
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
    #[Route('/', name: 'admin_offre_index')]
    public function index(OffreRepository $offreRepository, Security $security): Response
    {
        $entreprise = $security->getUser()->getEntreprise();
        return $this->render('admin/admin_offre/index.html.twig', [
            'offres' => $offreRepository->findBy(['entreprise' => $entreprise], ['id' => 'DESC']),
        ]);
    }

    // 2. CREER + EDIT
    #[Route('/new', name: 'admin_offre_new')]
    #[Route('/{id}/edit', name: 'admin_offre_edit')]
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
            return $this->redirectToRoute('admin_offre_index');
        }

        return $this->render('admin/admin_offre/form.html.twig', [
            'form' => $form->createView(),
            'offre' => $offre,
        ]);
    }
}

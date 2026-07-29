<?php

namespace App\Controller\Admin;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/conge')]
#[IsGranted('ROLE_ADMIN')]
class CongeController extends AbstractController
{
    #[Route('/', name: 'app_admin_conge_index')] // Fixed route name mismatch
    public function index(CongeRepository $congeRepo): Response
    {
        $user = $this->getUser();

        // Safety check if user is linked to an enterprise
        if (!$user || !$user->getEntreprise()) {
            throw $this->createAccessDeniedException('Aucune entreprise associée à votre compte.');
        }

        return $this->render('admin/conge/index.html.twig', [
            'conges' => $congeRepo->findBy(['entreprise' => $user->getEntreprise()], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_conge_valider', methods: ['POST'])] // Restricted to POST + CSRF recommended
    public function valider(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        // Multi-tenant security check: Ensure conge belongs to admin's company
        if ($conge->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        // Optional: CSRF token validation check if using forms/tokens in twig
        // if ($this->isCsrfTokenValid('valider_conge_'.$conge->getId(), $request->request->get('_token'))) { ... }

        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Congé validé avec succès.');
        return $this->redirectToRoute('app_admin_conge_index');
    }

    #[Route('/{id}/refuser', name: 'app_admin_conge_refuser', methods: ['POST'])] // Restricted to POST
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        // Multi-tenant security check
        if ($conge->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable()); // Good practice to track when it was refused too
        $em->flush();

        $this->addFlash('danger', 'Congé refusé.');
        return $this->redirectToRoute('app_admin_conge_index');
    }
}

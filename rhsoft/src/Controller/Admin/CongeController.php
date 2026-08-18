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
    #[Route('/', name: 'app_admin_conge')]
    public function index(CongeRepository $congeRepo): Response
    {
        $user = $this->getUser();

        // check if user is linked to an enterprise
        if (!$user || !$user->getEntreprise()) {
            throw $this->createAccessDeniedException('Aucune entreprise associée à votre compte.');
        }

        return $this->render('admin/conge/index.html.twig', [
            'conges' => $congeRepo->findBy(
                ['entreprise' => $user->getEntreprise()],
                ['createdAt' => 'DESC']
            ),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_conge_valider', methods: ['POST'])]
    public function valider(
        Request $request, Conge $conge,
        EntityManagerInterface $em
    ): Response
    {

        if ($conge->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Congé validé avec succès.');
        return $this->redirectToRoute('app_admin_conge');
    }

    #[Route('/{id}/refuser', name: 'app_admin_conge_refuser', methods: ['POST'])] // Restricted to POST
    public function refuser(
        Request $request,
        Conge $conge,
        EntityManagerInterface $em
    ): Response
    {

        if ($conge->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }

        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé.');
        return $this->redirectToRoute('app_admin_conge');
    }
}

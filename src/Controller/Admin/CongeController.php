<?php

namespace App\Controller\Admin;

use App\Entity\Conge;
use App\Repository\CongeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/conge')]
#[IsGranted('ROLE_ADMIN')]
class CongeController extends AbstractController
{
    #[Route('/', name: 'app_admin_conge_index')]
    public function index(CongeRepository $congeRepo): Response
    {
        return $this->render('admin/conge/index.html.twig', [
            'conges' => $congeRepo->findBy(['entreprise' => $this->getUser()->getEntreprise()], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_conge_valider')]
    public function valider(Conge $conge, EntityManagerInterface $em): Response
    {
        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('success', 'Congé validé');
        return $this->redirectToRoute('app_admin_conge_index');
    }

    #[Route('/{id}/refuser', name: 'app_admin_conge_refuser')]
    public function refuser(Conge $conge, EntityManagerInterface $em): Response
    {
        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setValidePar($this->getUser());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé');
        return $this->redirectToRoute('app_admin_conge_index');
    }
}

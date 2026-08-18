<?php

namespace App\Controller\Manager;

use App\Entity\Conge;
use App\Form\CongeType;
use App\Repository\CongeRepository;
use App\Repository\PaieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/manager')]
#[IsGranted('ROLE_MANAGER')]
final class ProfileController extends AbstractController
{
    #[Route('/profile', name: 'app_manager_profile')]
    public function index(): Response
    {
        return $this->render('manager/profile/index.html.twig');
    }

    #[Route('/solde', name: 'app_manager_profile_solde')]
    public function solde(CongeRepository $congeRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Fetch leave records associated with the logged-in user
        $conges = $congeRepository->findBy(['employee' => $user], ['id' => 'DESC']);

        return $this->render('manager/profile/solde.html.twig', [
            'user' => $user,
            'conges' => $conges,
        ]);
    }

    #[Route('/paie', name: 'app_manager_profile_paie')]
    public function paie(PaieRepository $paieRepository): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        // Fetch payroll records associated with the logged-in user
        $paies = $paieRepository->findBy(['employee' => $user], ['id' => 'DESC']);

        return $this->render('manager/profile/paie.html.twig', [
            'user' => $user,
            'paies' => $paies,
        ]);
    }

    #[Route('/conge/nouveau', name: 'app_manager_conge_new')]
    public function newConge(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();

        $conge = new Conge();
        $conge->setEmployee($user);
        $conge->setEntreprise($user->getEntreprise()); // Assuming your User entity has getEntreprise()
        $conge->setStatut('En attente');
        $conge->setCreatedAt(new \DateTimeImmutable());

        $form = $this->createForm(CongeType::class, $conge);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($conge);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande de congé a été soumise avec succès.');

            return $this->redirectToRoute('app_manager_profile_solde');
        }

        return $this->render('manager/profile/conge_new.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}

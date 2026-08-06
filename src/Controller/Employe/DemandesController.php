<?php

namespace App\Controller\Employe;

use App\Entity\AvanceSalaire;
use App\Form\Employe\AvanceSalaireType;
use App\Repository\AvanceSalaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_USER')]
final class DemandesController extends AbstractController
{
    #[Route('/avance', name: 'app_employe_demandes_avance', methods: ['GET', 'POST'])]
    public function avance(
        Request $request,
        EntityManagerInterface $entityManager,
        AvanceSalaireRepository $avanceRepository
    ): Response
    {
        $user = $this->getUser();

        $avance = new AvanceSalaire();
        $form = $this->createForm(AvanceSalaireType::class, $avance);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $avance->setEmployee($user);
            $avance->setEntreprise($user->getEntreprise()); // Assuming your User entity has getEntreprise()
            $avance->setStatut(AvanceSalaire::STATUS_DEMANDE);

            $entityManager->persist($avance);
            $entityManager->flush();

            $this->addFlash('success', 'Votre demande d\'avance a été envoyée avec succès.');

            return $this->redirectToRoute('app_employe_demandes_avance');
        }

        // Fetch past requests for the logged-in user
        $demandesPrecedentes = $avanceRepository->findBy(['user' => $user], ['dateDemande' => 'DESC']);

        return $this->render('employe/demandes/avance.html.twig', [
            'form' => $form->createView(),
            'demandesPrecedentes' => $demandesPrecedentes,
        ]);
    }

    #[Route('/demission', name: 'app_employe_demandes_demission')]
    public function demission(): Response
    {
        return $this->render('employe/demandes/demission.html.twig', [
            'controller_name' => 'Employe/DemandesController',
        ]);
    }
}

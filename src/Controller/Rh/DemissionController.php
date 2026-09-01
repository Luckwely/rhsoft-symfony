<?php

namespace App\Controller\Rh;

use App\Entity\Demission;
use App\Form\DemissionType;
use App\Repository\DemissionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

/**
 * Permet au RH de soumettre sa propre lettre de démission, comme n'importe quel employé.
 * Les demandes soumises ici passent ensuite par le circuit de validation Admin habituel
 * (via app_admin_demission_list).
 */
#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class DemissionController extends AbstractController
{
    #[Route('/demission', name: 'app_rh_demission', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        EntityManagerInterface $entityManager,
        DemissionRepository $demissionRepository,
        PaginatorInterface $paginator
    ): Response
    {
        $user = $this->getUser();

        $demission = new Demission();
        $form = $this->createForm(DemissionType::class, $demission);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dateDepart = $demission->getDateDepart();
            $today = new \DateTimeImmutable('today');
            if ($dateDepart === null || $dateDepart <= $today) {
                $this->addFlash('error', 'La date de départ doit être strictement ultérieure à aujourd’hui.');
                return $this->redirectToRoute('app_rh_demission');
            }

            $demission->setEmployee($user);
            $demission->setEntreprise($user->getEntreprise());
            $demission->setDateDemande(new \DateTimeImmutable());
            $demission->setStatut(Demission::STATUS_EN_ATTENTE);

            $entityManager->persist($demission);
            $entityManager->flush();

            $this->addFlash('success', 'Votre lettre de démission a été envoyée avec succès.');

            return $this->redirectToRoute('app_rh_demission');
        }

        $demissionsPrecedentes = $paginator->paginate(
            $demissionRepository->findBy(['employee' => $user], ['dateDemande' => 'DESC']),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('rh/demission/index.html.twig', [
            'form' => $form->createView(),
            'demissionsPrecedentes' => $demissionsPrecedentes,
            'pagination' => $demissionsPrecedentes,
        ]);
    }
}

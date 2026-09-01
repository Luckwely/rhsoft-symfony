<?php

namespace App\Controller\Admin;

use App\Entity\Conge;
use App\Entity\Demission;
use App\Entity\Entreprise;
use App\Form\Admin\CongeAdminType;
use App\Form\Admin\DemissionAdminType;
use App\Repository\CongeRepository;
use App\Repository\DemissionRepository;
use App\Service\CongeManagerService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin/conge')]
#[IsGranted('ROLE_ADMIN')]
class DemandeController extends AbstractController
{
    #[Route('/', name: 'app_admin_conge')]
    public function index(CongeRepository $congeRepo, PaginatorInterface $paginator, Request $request): Response
    {
        $conges = $paginator->paginate(
            $congeRepo->createQueryBuilder('c')
                ->andWhere('c.entreprise = :entreprise')
                ->setParameter('entreprise', $this->getEntreprise())
                ->orderBy('c.createdAt', 'DESC'),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/demande/conge/index.html.twig', [
            'conges' => $conges,
        ]);
        /* return $this->render('admin/demande/conge/index.html.twig', [
            'conges' => $congeRepo->findBy(
                ['entreprise' => $this->getEntreprise()],
                ['createdAt' => 'DESC']
            ),
        ]); */
    }

    #[Route('/new', name: 'app_admin_conge_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getEntreprise();

        $conge = new Conge();
        $conge->setEntreprise($entreprise);

        $form = $this->createForm(CongeAdminType::class, $conge, ['entreprise' => $entreprise]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($conge->getDateDebut() && $conge->getDateFin()) {
                $nbJours = $conge->getDateDebut()->diff($conge->getDateFin())->days + 1;
                $conge->setNbJours((float) $nbJours);
            }
            $conge->setStatut(Conge::STATUS_DEMANDE);

            $em->persist($conge);
            $em->flush();

            $this->addFlash('success', 'La demande de congé a été créée avec succès.');
            return $this->redirectToRoute('app_admin_conge');
        }

        return $this->render('admin/demande/conge/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/valider', name: 'app_admin_conge_valider', methods: ['POST'])]
    public function valider(Request $request, Conge $conge, EntityManagerInterface $em, CongeManagerService $congeManager): Response
    {
        $this->denyAccessUnlessSameEntreprise($conge->getEntreprise());
        $this->validateCsrf('conge_action_'.$conge->getId(), $request);

        if ($conge->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas approuver votre propre demande de congé.');
        }

        try {
            // Re-validation à l'approbation : ancienneté/solde sont re-vérifiés, mais pas
            // la règle "date de début pas dans le passé" (cf. commentaire du service) —
            // sinon un simple délai de traitement rend la demande définitivement bloquée.
            $congeManager->validateLeaveRequestForApproval($conge, $conge->getEmployee());
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
            return $this->redirectToRoute('app_admin_conge');
        }

        $conge->setStatut(Conge::STATUS_VALIDE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        // Répercute automatiquement le congé sur le planning de l'employé (jours verrouillés)
        $congeManager->syncPlanningForValidatedConge($conge);

        $this->addFlash('success', 'Congé validé avec succès.');
        return $this->redirectToRoute('app_admin_conge');
    }

    #[Route('/{id}/refuser', name: 'app_admin_conge_refuser', methods: ['POST'])]
    public function refuser(Request $request, Conge $conge, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessSameEntreprise($conge->getEntreprise());
        $this->validateCsrf('conge_action_'.$conge->getId(), $request);

        if ($conge->getEmployee() === $this->getUser()) {
            throw $this->createAccessDeniedException('Vous ne pouvez pas rejeter votre propre demande de congé.');
        }

        $conge->setStatut(Conge::STATUS_REFUSE);
        $conge->setValidePar($this->getUser());
        $conge->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Congé refusé.');
        return $this->redirectToRoute('app_admin_conge');
    }

    #[Route('/demission', name: 'app_admin_demission_list')]
    public function demission(DemissionRepository $demissionRepo, PaginatorInterface $paginator, Request $request): Response
    {
        $demissions = $paginator->paginate(
            $demissionRepo->createByEntrepriseQueryBuilder($this->getEntreprise()),
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/demande/demission/index.html.twig', [
            'demissions' => $demissions,
        ]);
    }

    #[Route('/demission/new', name: 'app_admin_demission_new', methods: ['GET', 'POST'])]
    public function newDemission(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getEntreprise();

        $demission = new Demission();
        $demission->setEntreprise($entreprise);
        $demission->setDateDemande(new \DateTimeImmutable());

        $form = $this->createForm(DemissionAdminType::class, $demission, ['entreprise' => $entreprise]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $demission->setStatut(Demission::STATUS_EN_ATTENTE);

            $em->persist($demission);
            $em->flush();

            $this->addFlash('success', 'La démission a été enregistrée avec succès.');
            return $this->redirectToRoute('app_admin_demission_list');
        }

        return $this->render('admin/demande/demission/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/demission/{id}/valider', name: 'app_admin_demission_valider', methods: ['POST'])]
    public function validerDemission(Request $request, Demission $demission, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessSameEntreprise($demission->getEntreprise());
        $this->validateCsrf('demission_action_'.$demission->getId(), $request);

        $demission->setStatut(Demission::STATUS_VALIDEE);
        $demission->setValidePar($this->getUser());
        $demission->setValideLe(new \DateTimeImmutable());

        // The employee remains active during the notice period and leaves at the
        // end of the approved departure date.
        $employee = $demission->getEmployee();
        if ($employee && $demission->getDateDepart()) {
            $employee->setDateSortie(
                new \DateTime(\DateTimeImmutable::createFromInterface($demission->getDateDepart())->format('Y-m-d 23:59:59'))
            );
        }

        $em->flush();

        $this->addFlash('success', 'Démission validée avec succès.');
        return $this->redirectToRoute('app_admin_demission_list');
    }

    #[Route('/demission/{id}/refuser', name: 'app_admin_demission_refuser', methods: ['POST'])]
    public function refuserDemission(Request $request, Demission $demission, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessSameEntreprise($demission->getEntreprise());
        $this->validateCsrf('demission_action_'.$demission->getId(), $request);

        $demission->setStatut(Demission::STATUS_REFUSEE);
        $demission->setValidePar($this->getUser());
        $demission->setValideLe(new \DateTimeImmutable());
        $em->flush();

        $this->addFlash('danger', 'Démission refusée.');
        return $this->redirectToRoute('app_admin_demission_list');
    }

    private function getEntreprise(): Entreprise
    {
        $user = $this->getUser();

        if (!$user || !method_exists($user, 'getEntreprise') || !$user->getEntreprise()) {
            throw $this->createAccessDeniedException('Aucune entreprise associée à votre compte.');
        }

        return $user->getEntreprise();
    }

    private function denyAccessUnlessSameEntreprise(?Entreprise $entreprise): void
    {
        if (!$entreprise || $entreprise !== $this->getEntreprise()) {
            throw $this->createAccessDeniedException('Action non autorisée.');
        }
    }

    private function validateCsrf(string $tokenId, Request $request): void
    {
        if (!$this->isCsrfTokenValid($tokenId, $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
    }
}

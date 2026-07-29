<?php

namespace App\Controller\Admin;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class ParametreController extends AbstractController
{
    #[Route('/parametre', name: 'app_admin_parametre', methods: ['GET', 'POST'])]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $entreprise = $user->getEntreprise();

        if (!$entreprise) {
            $this->addFlash('error', 'Aucune entreprise associée à votre compte.');
            return $this->redirectToRoute('app_admin_dashboard');
        }

        // Handle form submissions from the different parameter tabs
        if ($request->isMethod('POST')) {
            $actionType = $request->request->get('action_type');

            if ($actionType === 'entreprise') {
                $entreprise->setNom($request->request->get('nom'));
                $entreprise->setAdresse($request->request->get('adresse'));
                $entreprise->setTel($request->request->get('telephone'));
                $entreprise->setNif($request->request->get('nif'));

                $entityManager->flush();
                $this->addFlash('success', 'Informations de l\'entreprise mises à jour avec succès.');
            }
            elseif ($actionType === 'pointage') {
                $entreprise->setToleranceRetard((int) $request->request->get('tolerance_retard'));

                $entityManager->flush();
                $this->addFlash('success', 'Règles de pointage mises à jour avec succès.');
            }

            return $this->redirectToRoute('app_admin_parametre');
        }

        // Fetch users belonging to this specific company for the "Utilisateurs" tab
        $users = $entityManager->getRepository(User::class)->findBy(['entreprise' => $entreprise]);

        return $this->render('admin/parametre/index.html.twig', [
            'entreprise' => $entreprise,
            'users' => $users,
        ]);
    }

    #[Route('/user/{id}/toggle-suspend', name: 'admin_user_toggle_suspend', methods: ['POST'])]
    public function toggleSuspend(User $targetUser, Request $request, EntityManagerInterface $entityManager): Response
    {
        $token = $request->request->get('token');
        if (!$this->isCsrfTokenValid('suspend_user_' . $targetUser->getId(), $token)) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_admin_parametre');
        }

        // Toggle the is_active state (true becomes false, false becomes true)
        $newStatus = !$targetUser->isActive();
        $targetUser->setIsActive($newStatus);
        $entityManager->flush();

        $statusMessage = $newStatus ? 'réactivé' : 'suspendu';
        $this->addFlash('success', "L'utilisateur a été {$statusMessage} avec succès.");

        return $this->redirectToRoute('app_admin_parametre');
    }

    #[Route('/user/{id}/update-role', name: 'admin_user_update_role', methods: ['POST'])]
    public function updateRole(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // 1. Multi-tenant security check
        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet utilisateur n\'appartient pas à votre entreprise.');
        }

        // 2. CSRF Token validation matching the Twig form token
        $token = $request->request->get('token');
        if (!$this->isCsrfTokenValid('role_user_' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_parametres'); // Replace with your actual settings route name
        }

        // 3. Update the array role
        $newRole = $request->request->get('role');
        $allowedRoles = ['ROLE_EMPLOYE', 'ROLE_MANAGER', 'ROLE_RH', 'ROLE_ADMIN'];

        if (in_array($newRole, $allowedRoles)) {
            $user->setRoles([$newRole]);
            $em->flush();

            $this->addFlash('success', 'Rôle mis à jour avec succès.');
        } else {
            $this->addFlash('danger', 'Rôle non valide.');
        }

        // 4. Redirect back to the settings page
        return $this->redirectToRoute('app_admin_parametre'); // Replace with your actual settings route name
    }
}

<?php
namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Planning;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\EmployeeFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;


#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class EmployeeController extends AbstractController
{
    #[Route('/employees', name: 'app_admin_employee')]
    public function list(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {

        $entreprise = $this->getUser()->getEntreprise();

        $mondayThisWeek = new \DateTime('monday this week');
        $plannings = $em->getRepository(Planning::class)->findBy([
            'entreprise' => $entreprise,
            'weekStart' => $mondayThisWeek
        ]);
        $planningMap = [];
        foreach($plannings as $p){
            $planningMap[$p->getUser()->getId()] = $p;
        }

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true);

        // RECHERCHE
        if ($search = $request->query->get('q')) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $employees = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('admin/employee/list.html.twig', [
            'employees' => $employees,
            'planningMap' => $planningMap
        ]);
    }

    #[Route('/employees/new', name: 'app_admin_employee_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        string $upload_dir
    ): Response
    {
        $admin = $this->getUser();
        $entreprise = $admin->getEntreprise();

        $user = new User();

        // Pre-fill fields if passed from query parameters (accepted candidature)
        if ($request->query->has('nom')) {
            $user->setNom($request->query->get('nom'));
        }
        if ($request->query->has('email')) {
            $user->setEmail($request->query->get('email'));
        }
        if ($request->query->has('telephone')) {
            $user->setTelephone($request->query->get('telephone'));
        }

        $form = $this->createForm(EmployeeFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // On set ce qui n'est pas dans le form
            $user->setEntreprise($entreprise);
            $user->setIsActive(false);
            $user->setIsVerified(false);

            // UPLOAD PHOTO
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid().'.'.$photoFile->guessExtension();
                $photoFile->move($upload_dir, $newFilename);
                $user->setPhoto($newFilename);
            }

            // UPLOAD CV
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = uniqid().'.'.$cvFile->guessExtension();
                $cvFile->move($upload_dir, $newFilename);
                $user->setCv($newFilename);
            }

            // TOKEN
            $token = bin2hex(random_bytes(32));
            $user->setInvitationToken($token);
            $user->setInvitationExpiresAt(new \DateTimeImmutable('+48 hours'));

            $em->persist($user);
            $em->flush();

            // EMAIL
            $url = $this->generateUrl('app_invite_accept', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
            $email = (new Email())
                ->from('no-reply@rhsoft.mg')
                ->to($user->getEmail())
                ->subject('Invitation RhSoft - Rejoignez votre entreprise')
                ->html($this->renderView('emails/invitation.html.twig', ['user' => $user, 'url' => $url ]));

            try {
                $mailer->send($email);
                $this->addFlash('success', 'Invitation envoyée à ' . $user->getEmail());
            } catch (\Exception $e) {
                $this->addFlash('danger', 'Erreur email: ' . $e->getMessage());
            }

            return $this->redirectToRoute('app_admin_employee');
        }

        return $this->render('admin/employee/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/employees/{id}/edit', name: 'app_admin_employee_edit')]
    public function edit(Request $request, User $employee, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EmployeeFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // Gestion Upload Photo
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid().'.'.$photoFile->guessExtension();
                $photoFile->move($this->getParameter('employees_directory'), $newFilename);
                $employee->setPhoto($newFilename);
            }

            // Gestion Upload CV
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = uniqid().'.'.$cvFile->guessExtension();
                $cvFile->move($this->getParameter('employees_directory'), $newFilename);
                $employee->setCv($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Employé modifié');
            return $this->redirectToRoute('app_admin_employee');
        }

        return $this->render('admin/employee/edit.html.twig', [
            'employee' => $employee,
            'form' => $form->createView(), // <-- IL MANQUAIT CETTE LIGNE
        ]);
    }

    #[Route('/employees/{id}/delete', name: 'app_admin_employee_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) { // <-- FIRST
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }
        if ($this->isCsrfTokenValid('delete'.$user->getId(), $request->request->get('_token'))) {
            $em->remove($user);
            $em->flush();
            $this->addFlash('success', 'Employé supprimé.');
        }
        return $this->redirectToRoute('app_admin_employee');
    }

    #[Route('/employees/{id}/resend', name: 'app_admin_employee_resend')]
    public function resend(
        User $user,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        // 1. Generate new token
        $token = bin2hex(random_bytes(32));
        $user->setInvitationToken($token);
        $user->setInvitationExpiresAt(new \DateTimeImmutable('+48 hours'));
        $em->flush();

        // 2. Resend email
        $url = $this->generateUrl('app_invite_accept', ['token' => $token], UrlGeneratorInterface::ABSOLUTE_URL);
        $email = (new Email())
            ->from('no-reply@rhsoft.mg')
            ->to($user->getEmail())
            ->subject('Rappel: Invitation RhSoft')
            ->html($this->renderView('emails/invitation.html.twig', ['user' => $user, 'url' => $url ]));

        $mailer->send($email);


        $this->addFlash('success', 'Invitation renvoyée à ' . $user->getEmail());

        return $this->redirectToRoute('app_admin_employee');
    }

    #[Route('/employees/bulk-delete', name: 'app_admin_employee_bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if ($this->isCsrfTokenValid('bulk-delete', $request->request->get('_token'))) {
            $ids = $request->request->all('ids');
            $users = $em->getRepository(User::class)->findBy(['id' => $ids]);
            foreach ($users as $user) {
                if ($user->getEntreprise() === $this->getUser()->getEntreprise()) {
                    $em->remove($user);
                }
            }
            $em->flush();
            $this->addFlash('success', count($ids).' employé(s) supprimé(s)');
        }
        return $this->redirectToRoute('app_admin_employee');
    }

    #[Route('/employees/{id}/update-role', name: 'admin_user_update_role', methods: ['POST'])]
    public function updateRole(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // Multi-tenant security check
        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        // CSRF Token validation
        $token = $request->request->get('token');
        if (!$this->isCsrfTokenValid('role_user_' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_employee'); // Or your settings route name
        }

        $newRole = $request->request->get('role');
        $allowedRoles = ['ROLE_EMPLOYE', 'ROLE_MANAGER', 'ROLE_RH', 'ROLE_ADMIN'];

        if (in_array($newRole, $allowedRoles)) {
            // Update the array-based role property
            $user->setRoles([$newRole]);
            $em->flush();

            $this->addFlash('success', 'Rôle mis à jour avec succès.');
        } else {
            $this->addFlash('danger', 'Rôle non valide.');
        }

        // Redirect back to wherever your users table is displayed (e.g., settings or employee list)
        return $this->redirectToRoute('app_admin_employee');
    }
}

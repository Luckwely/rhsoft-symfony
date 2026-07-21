<?php
namespace App\Controller\Admin;

use App\Entity\User;
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

class EmployeeController extends AbstractController
{
    #[Route('/admin/employees', name: 'app_employee_list')]
    public function list(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $entreprise = $this->getUser()->getEntreprise();

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('role', '%ROLE_EMPLOYE%');

        // RECHERCHE
        if ($search = $request->query->get('q')) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $employees = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1), // Page actuelle
            10 // Nb d'éléments par page
        );

        return $this->render('admin/employee/list.html.twig', [
            'employees' => $employees
        ]);
    }

    #[Route('/admin/employees/new', name: 'app_employee_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        MailerInterface $mailer,
        string $upload_dir
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        $admin = $this->getUser();
        $entreprise = $admin->getEntreprise();

        $user = new User(); // 1. Créer l'objet AVANT
        $form = $this->createForm(EmployeeFormType::class, $user); // 2. Créer le form AVANT
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {

            // On set ce qui n'est pas dans le form
            $user->setEntreprise($entreprise);
            $user->setRoles(['ROLE_EMPLOYE']);
            $user->setIsActive(false);
            $user->setIsVerified(false);
            $user->setStatut('inactif');

            // UPLOAD PHOTO
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid().'.'.$photoFile->guessExtension();
                $photoFile->move($upload_dir, $newFilename);
                $user->setPhoto($newFilename);
            }

            // UPLOAD CV - BUG CORRIGE ICI
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) { // <-- $cvFile pas $photoFile
                $newFilename = uniqid().'.'.$cvFile->guessExtension();
                $cvFile->move($upload_dir, $newFilename);
                $user->setCv($newFilename);
            }

            // TOKEN
            $token = bin2hex(random_bytes(32));
            $user->setInvitationToken($token);
            $user->setInvitationExpiresAt(new \DateTimeImmutable('+48 hours'));

            $em->persist($user); // 3. Un seul persist
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

            return $this->redirectToRoute('app_employee_list');
        }

        return $this->render('admin/employee/new.html.twig', [
            'form' => $form->createView(), // 4. Toujours passer le form
        ]);
    }

    #[Route('/admin/employees/{id}/edit', name: 'app_employee_edit')]
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
            return $this->redirectToRoute('app_employee_list');
        }

        return $this->render('admin/employee/edit.html.twig', [
            'employee' => $employee,
            'form' => $form->createView(), // <-- IL MANQUAIT CETTE LIGNE
        ]);
    }

    #[Route('/admin/employees/{id}/delete', name: 'app_employee_delete', methods: ['POST'])]
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
        return $this->redirectToRoute('app_employee_list');
    }

    #[Route('/admin/employees/{id}/resend', name: 'app_employee_resend')]
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

        return $this->redirectToRoute('app_employee_list');
    }

    #[Route('/admin/employees/bulk-delete', name: 'app_employee_bulk_delete', methods: ['POST'])]
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
        return $this->redirectToRoute('app_employee_list');
    }
}

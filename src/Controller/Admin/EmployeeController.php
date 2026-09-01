<?php
namespace App\Controller\Admin;

use App\Entity\User;
use App\Entity\Planning;
use App\Entity\Pointage;
use App\Entity\Conge;
use App\Entity\Demission;
use App\Entity\Paie;
use App\Entity\AvanceSalaire;
use App\Entity\Notification;
use App\Service\SubscriptionLimitService;
use App\Service\EmployeeCsvImportService;
use Doctrine\ORM\EntityManagerInterface;
use App\Form\EmployeeFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
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

        $mondayThisWeek = new \DateTimeImmutable('monday this week');
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
            //->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise);
            //->setParameter('active', true);

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
        SubscriptionLimitService $subscriptionLimitService,
        string $upload_dir
    ): Response
    {
        $admin = $this->getUser();
        $entreprise = $admin->getEntreprise();

        if (!$subscriptionLimitService->canAddEmployee($entreprise)) {
            $this->addFlash('danger', $subscriptionLimitService->getEmployeeLimitReachedMessage($entreprise));

            return $this->redirectToRoute('app_admin_employee');
        }

        $user = new User();

        // Pre-fill fields if passed from query parameters (accepted candidature)
        if ($request->query->has('nom')) {
            $user->setNom($request->query->get('nom'));
        }
        if ($request->query->has('prenom')) {
            $user->setPrenom($request->query->get('prenom'));
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

            $user->setEntreprise($entreprise);
            $user->setIsActive(false);
            $user->setIsVerified(false);
            $user->setSalaireBase($entreprise->getSalaireBaseForRoles($user->getRoles()));

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
                ->html($this->renderView('emails/invitation.html.twig', [
                    'user' => $user,
                    'url' => $url
                ]));


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

    #[Route('/employees/import', name: 'app_admin_employee_import')]
    public function import(
        Request $request,
        EmployeeCsvImportService $csvImportService,
        MailerInterface $mailer,
        SubscriptionLimitService $subscriptionLimitService
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $results = null;

        if ($request->isMethod('POST')) {
            /** @var UploadedFile|null $csvFile */
            $csvFile = $request->files->get('csv_file');
            $sendInvitations = (bool) $request->request->get('send_invitations');

            if (!$csvFile) {
                $this->addFlash('danger', 'Veuillez sélectionner un fichier CSV à importer.');

                return $this->redirectToRoute('app_admin_employee_import');
            }

            $extension = strtolower($csvFile->getClientOriginalExtension() ?: '');
            if (!in_array($extension, ['csv', 'txt'], true)) {
                $this->addFlash('danger', 'Le fichier doit être au format CSV (.csv).');

                return $this->redirectToRoute('app_admin_employee_import');
            }

            if (!$subscriptionLimitService->canAddEmployee($entreprise)) {
                $this->addFlash('danger', $subscriptionLimitService->getEmployeeLimitReachedMessage($entreprise));

                return $this->redirectToRoute('app_admin_employee_import');
            }

            $results = $csvImportService->import($csvFile->getPathname(), $entreprise);

            if ($sendInvitations && !empty($results['success'])) {
                $results['errors'] = array_merge(
                    $results['errors'],
                    $this->sendBulkInvitations($results['success'], $mailer)
                );
            }

            // On retire la référence à l'entité avant de passer au template
            $results['success'] = array_map(static fn(array $entry) => [
                'line' => $entry['line'],
                'nom' => $entry['nom'],
                'prenom' => $entry['prenom'],
                'email' => $entry['email'],
            ], $results['success']);

            if (!empty($results['success'])) {
                $this->addFlash('success', sprintf(
                    '%d employé(s) importé(s) avec succès%s.',
                    count($results['success']),
                    $sendInvitations ? ' et invitation(s) envoyée(s)' : ''
                ));
            }
            if (!empty($results['errors'])) {
                $this->addFlash('danger', sprintf(
                    '%d ligne(s) n\'ont pas pu être importées. Voir le détail ci-dessous.',
                    count($results['errors'])
                ));
            }
        }

        return $this->render('admin/employee/import.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/employees/import/modele', name: 'app_admin_employee_import_template')]
    public function importTemplate(EmployeeCsvImportService $csvImportService): Response
    {
        return new Response($csvImportService->buildTemplateCsv(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="modele-import-employes.csv"',
        ]);
    }

    /**
     * Sends the invitation email to each freshly-imported user.
     * Returns an array of error entries (same shape as the import errors)
     * for any invitation that failed to send.
     */
    private function sendBulkInvitations(array $importedEntries, MailerInterface $mailer): array
    {
        $errors = [];

        foreach ($importedEntries as $entry) {
            /** @var User $user */
            $user = $entry['user'];
            $url = $this->generateUrl('app_invite_accept', ['token' => $user->getInvitationToken()], UrlGeneratorInterface::ABSOLUTE_URL);
            $email = (new Email())
                ->from('no-reply@rhsoft.mg')
                ->to($user->getEmail())
                ->subject('Invitation RhSoft - Rejoignez votre entreprise')
                ->html($this->renderView('emails/invitation.html.twig', ['user' => $user, 'url' => $url]));

            try {
                $mailer->send($email);
            } catch (\Exception $e) {
                $errors[] = ['line' => 0, 'message' => "Employé {$user->getEmail()} importé, mais l'email d'invitation n'a pas pu être envoyé (" . $e->getMessage() . ')'];
            }
        }

        return $errors;
    }

    #[Route('/employees/{id}/edit', name: 'app_admin_employee_edit')]
    public function edit(
        Request $request,
        User $employee,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(EmployeeFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $employee->setSalaireBase($employee->getEntreprise()->getSalaireBaseForRoles($employee->getRoles()));


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
            'form' => $form->createView(),
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
        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }
        if (!$this->isCsrfTokenValid('delete'.$user->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide. Suppression annulée.');
            return $this->redirectToRoute('app_admin_employee');
        }
        if ($user === $this->getUser()) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('app_admin_employee');
        }
        $this->removeEmployeeAndDependencies($user, $em);
        $em->flush();
        $this->addFlash('success', 'Employé supprimé.');
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
    public function bulkDelete(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        if (!$this->isCsrfTokenValid('bulk-delete', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton CSRF invalide. Suppression annulée.');
            return $this->redirectToRoute('app_admin_employee');
        }
        $ids = array_values(array_filter(array_map('intval', (array) $request->request->all('ids'))));
        $users = $em->getRepository(User::class)->findBy(['id' => $ids]);
        $deleted = 0;
        foreach ($users as $user) {
            if ($user !== $this->getUser() && $user->getEntreprise() === $this->getUser()->getEntreprise()) {
                $this->removeEmployeeAndDependencies($user, $em);
                $deleted++;
            }
        }
        $em->flush();
        $this->addFlash('success', $deleted.' employé(s) supprimé(s)');
        return $this->redirectToRoute('app_admin_employee');
    }

    private function removeEmployeeAndDependencies(User $user, EntityManagerInterface $em): void
    {
        foreach ($em->getRepository(Pointage::class)->findBy(['employee' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Planning::class)->findBy(['user' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Conge::class)->findBy(['employee' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Demission::class)->findBy(['employee' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Paie::class)->findBy(['employee' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(AvanceSalaire::class)->findBy(['employee' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Notification::class)->findBy(['destinataire' => $user]) as $item) { $em->remove($item); }
        foreach ($em->getRepository(Paie::class)->findBy(['validePar' => $user]) as $item) { $item->setValidePar(null); }
        foreach ($em->getRepository(AvanceSalaire::class)->findBy(['validePar' => $user]) as $item) { $item->setValidePar(null); }
        foreach ($em->getRepository(AvanceSalaire::class)->findBy(['payePar' => $user]) as $item) { $item->setPayePar(null); }
        foreach ($em->getRepository(Demission::class)->findBy(['validePar' => $user]) as $item) { $item->setValidePar(null); }
        foreach ($em->getRepository(Pointage::class)->findBy(['corrigePar' => $user]) as $item) { $item->setCorrigePar(null); }
        $em->remove($user);
    }

    #[Route('/employees/{id}/update-role', name: 'admin_employee_update_role', methods: ['POST'])]
    public function updateRole(
        Request $request,
        User $user,
        EntityManagerInterface $em
    ): Response
    {

        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        // CSRF Token validation
        $token = $request->request->get('token');
        if (!$this->isCsrfTokenValid('role_user_' . $user->getId(), $token)) {
            $this->addFlash('danger', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_employee');
        }

        $newRole = $request->request->get('role');
        $allowedRoles = ['ROLE_EMPLOYE', 'ROLE_MANAGER', 'ROLE_RH', 'ROLE_ADMIN'];

        if (in_array($newRole, $allowedRoles)) {

            $user->setRoles([$newRole]);
            $em->flush();

            $this->addFlash('success', 'Rôle mis à jour avec succès.');
        } else {
            $this->addFlash('danger', 'Rôle non valide.');
        }


        return $this->redirectToRoute('app_admin_employee');
    }
}

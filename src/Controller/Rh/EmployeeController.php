<?php

namespace App\Controller\Rh;

use App\Entity\User;
use App\Entity\Planning;
use App\Form\EmployeeFormType;
use App\Service\SubscriptionLimitService;
use App\Service\EmployeeCsvImportService;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Twig\Environment;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class EmployeeController extends AbstractController
{
    #[Route('/employee', name: 'app_rh_employee')]
    public function index(
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

        $employeeCount = (int) (clone $queryBuilder)
            ->select('COUNT(u.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $employees = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('rh/employee/index.html.twig', [
            'employees' => $employees,
            'employeeCount' => $employeeCount,
            'planningMap' => $planningMap,
            'q' => $request->query->get('q'),
        ]);
    }

    #[Route('/embauche', name: 'app_rh_employee_embauche')]
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

            return $this->redirectToRoute('app_rh_employee');
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

            $uploadDir = $this->getParameter('employees_directory');

            // UPLOAD PHOTO
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid().'.'.$photoFile->guessExtension();
                $photoFile->move($uploadDir, $newFilename);
                $user->setPhoto($newFilename);
            }

            // UPLOAD CV
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = uniqid().'.'.$cvFile->guessExtension();
                $cvFile->move($uploadDir, $newFilename);
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

            return $this->redirectToRoute('app_rh_employee');
        }

        return $this->render('rh/employee/embauche.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/employees/import', name: 'app_rh_employee_import', methods: ['GET', 'POST'])]
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
            // Toute création par import doit permettre à l’employé de définir son mot de passe.
            // L’invitation est donc envoyée systématiquement après chaque ligne importée.
            $sendInvitations = true;

            if (!$this->isCsrfTokenValid('rh_employee_import', (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
                return $this->redirectToRoute('app_rh_employee_import');
            }

            if (!$csvFile) {
                $this->addFlash('danger', 'Veuillez sélectionner un fichier CSV à importer.');

                return $this->redirectToRoute('app_rh_employee_import');
            }

            $extension = strtolower($csvFile->getClientOriginalExtension() ?: '');
            if (!in_array($extension, ['csv', 'txt'], true)) {
                $this->addFlash('danger', 'Le fichier doit être au format CSV (.csv).');

                return $this->redirectToRoute('app_rh_employee_import');
            }

            if (!$subscriptionLimitService->canAddEmployee($entreprise)) {
                $this->addFlash('danger', $subscriptionLimitService->getEmployeeLimitReachedMessage($entreprise));

                return $this->redirectToRoute('app_rh_employee_import');
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

        return $this->render('rh/employee/import.html.twig', [
            'results' => $results,
        ]);
    }

    #[Route('/employees/import/modele', name: 'app_rh_employee_import_template')]
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


    #[Route('/employees/{id}/edit', name: 'app_rh_employee_edit')]
    public function edit(Request $request, User $employee, EntityManagerInterface $em): Response
    {
        if ($employee->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        $form = $this->createForm(EmployeeFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $employee->setSalaireBase($employee->getEntreprise()->getSalaireBaseForRoles($employee->getRoles()));

            $uploadDir = $this->getParameter('employees_directory');

            // Gestion Upload Photo
            $photoFile = $form->get('photo')->getData();
            if ($photoFile) {
                $newFilename = uniqid().'.'.$photoFile->guessExtension();
                $photoFile->move($uploadDir, $newFilename);
                $employee->setPhoto($newFilename);
            }

            // Gestion Upload CV
            $cvFile = $form->get('cv')->getData();
            if ($cvFile) {
                $newFilename = uniqid().'.'.$cvFile->guessExtension();
                $cvFile->move($uploadDir, $newFilename);
                $employee->setCv($newFilename);
            }

            $em->flush();

            $this->addFlash('success', 'Employé modifié');
            return $this->redirectToRoute('app_rh_employee');
        }

        return $this->render('rh/employee/edit.html.twig', [
            'employee' => $employee,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/employees/{id}', name: 'app_rh_employee_show', methods: ['GET'])]
    public function show(User $employee): Response
    {
        if ($employee->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        return $this->render('rh/employee/show.html.twig', [
            'employee' => $employee,
        ]);
    }

    #[Route('/employees/{id}/resend', name: 'app_rh_employee_resend')]
    public function resend(
        User $user,
        EntityManagerInterface $em,
        MailerInterface $mailer
    ): Response
    {
        $this->denyAccessUnlessGranted('ROLE_RH');

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

        return $this->redirectToRoute('app_rh_employee');
    }

    #[Route('/sortie', name: 'app_rh_employee_sortie')]
    public function sortie(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();

        // Query builder for departed employees (inactive members belonging to the company)
        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateSortie IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('u.dateSortie', 'DESC')
            ->addOrderBy('u.id', 'DESC');

        // Optional search filter for departures if needed
        if ($search = $request->query->get('q')) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $sorties = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('rh/employee/sortie.html.twig', [
            'sorties' => $sorties,
        ]);
    }

    #[Route('/sortie/export', name: 'app_rh_employee_sortie_export', methods: ['GET'])]
    public function exportSorties(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateSortie IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('u.dateSortie', 'DESC');

        if ($search = trim((string) $request->query->get('q', ''))) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        $lines = ['Nom;Prénom;Email;Poste;Date de sortie;Motif'];
        foreach ($queryBuilder->getQuery()->getResult() as $employee) {
            $values = [
                $employee->getNom(),
                $employee->getPrenom(),
                $employee->getEmail(),
                $employee->getPoste() ?? '',
                $employee->getDateSortie()?->format('d/m/Y') ?? '',
                $employee->getMotifSortie() ?? '',
            ];
            $lines[] = implode(';', array_map(static fn(?string $value): string => '"'.str_replace('"', '""', (string) $value).'"', $values));
        }

        return new Response("\xEF\xBB\xBF".implode("\n", $lines), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="sorties-employes.csv"',
        ]);
    }

    #[Route('/employees/{id}/certificat', name: 'app_rh_employee_certificat')]
    public function certificat(User $user, Environment $twig): Response
    {
        // Security check: ensure employee belongs to the RH's enterprise
        if ($user->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Cet employé n\'appartient pas à votre entreprise.');
        }

        $html = $twig->render('rh/employee/certificat_pdf.html.twig', [
            'employee' => $user,
            'entreprise' => $user->getEntreprise(),
        ]);

        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = sprintf('certificat-travail-%s-%s.pdf', $user->getNom(), $user->getPrenom());

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . $filename . '"',
        ]);
    }
}

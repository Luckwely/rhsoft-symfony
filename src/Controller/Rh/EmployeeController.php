<?php

namespace App\Controller\Rh;

use App\Entity\User;
use App\Entity\Planning;
use App\Form\EmployeeFormType;
use Dompdf\Dompdf;
use Dompdf\Options;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Knp\Component\Pager\PaginatorInterface;
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

        $employees = $paginator->paginate(
            $queryBuilder,
            $request->query->getInt('page', 1),
            10
        );

        return $this->render('rh/employee/index.html.twig', [
            'employees' => $employees,
            'planningMap' => $planningMap
        ]);
    }

    #[Route('/embauche', name: 'app_rh_employee_embauche')]
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
            $user->setEntreprise($entreprise);
            $user->setIsActive(false);
            $user->setIsVerified(false);

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

    #[Route('/employees/{id}/edit', name: 'app_rh_employee_edit')]
    public function edit(Request $request, User $employee, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(EmployeeFormType::class, $employee);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', false)
            ->orderBy('u.id', 'DESC');

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

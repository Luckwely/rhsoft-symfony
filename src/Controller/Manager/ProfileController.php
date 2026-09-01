<?php

namespace App\Controller\Manager;

use App\Entity\Conge;
use App\Entity\Paie;
use App\Form\CongeType;
use App\Repository\CongeRepository;
use App\Repository\PaieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;
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
        return $this->redirectToRoute('app_manager_profile_solde');
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

    #[Route('/paie/{id}/download', name: 'app_manager_profile_paie_download', methods: ['GET'])]
    public function downloadPaie(Paie $paie, Environment $twig): Response
    {
        $this->assertOwnPayslip($paie);

        return $this->createPayslipPdfResponse($paie, $twig);
    }

    #[Route('/paie/download-all', name: 'app_manager_profile_paie_download_all', methods: ['GET'])]
    public function downloadAllPaies(PaieRepository $paieRepository, Environment $twig): Response
    {
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $paies = $paieRepository->findBy(['employee' => $user], ['annee' => 'DESC', 'mois' => 'DESC']);

        if (!$paies) {
            $this->addFlash('info', 'Aucune fiche de paie disponible à télécharger.');
            return $this->redirectToRoute('app_manager_profile_paie');
        }

        $temporaryFile = tempnam(sys_get_temp_dir(), 'rhsoft_paies_');
        $zip = new \ZipArchive();
        if ($temporaryFile === false || $zip->open($temporaryFile, \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Impossible de préparer l’archive des fiches de paie.');
        }

        foreach ($paies as $paie) {
            $pdf = $this->renderPayslipPdf($paie, $twig);
            $zip->addFromString(sprintf('fiche-de-paie-%02d-%d.pdf', $paie->getMois(), $paie->getAnnee()), $pdf);
        }
        $zip->close();
        $content = file_get_contents($temporaryFile);
        unlink($temporaryFile);

        return new Response($content ?: '', 200, [
            'Content-Type' => 'application/zip',
            'Content-Disposition' => 'attachment; filename="mes-fiches-de-paie.zip"',
        ]);
    }

    private function assertOwnPayslip(Paie $paie): void
    {
        if ($paie->getEmployee() !== $this->getUser()) {
            throw $this->createAccessDeniedException('Vous n’êtes pas autorisé à accéder à cette fiche de paie.');
        }
    }

    private function createPayslipPdfResponse(Paie $paie, Environment $twig): Response
    {
        $pdf = $this->renderPayslipPdf($paie, $twig);

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="fiche-de-paie-%02d-%d.pdf"', $paie->getMois(), $paie->getAnnee()),
        ]);
    }

    private function renderPayslipPdf(Paie $paie, Environment $twig): string
    {
        $employee = $paie->getEmployee();
        $html = $twig->render('employe/paie/payslip_pdf.html.twig', [
            'payslip' => $paie,
            'employee' => $employee,
            'entreprise' => $employee?->getEntreprise(),
        ]);
        $options = new Options();
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
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

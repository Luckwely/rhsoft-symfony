<?php

namespace App\Controller\Admin;

use App\Entity\AvanceSalaire;
use App\Entity\Paie;
use App\Entity\User;
use App\Repository\AvanceSalaireRepository;
use App\Repository\PaieRepository;
use App\Repository\PointageRepository;
use App\Service\PaieCalculatorService;
use App\Service\PayslipService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class PaieController extends AbstractController
{
    private const MOIS_LABELS = [
        1 => 'Janvier', 2 => 'Février', 3 => 'Mars', 4 => 'Avril',
        5 => 'Mai', 6 => 'Juin', 7 => 'Juillet', 8 => 'Août',
        9 => 'Septembre', 10 => 'Octobre', 11 => 'Novembre', 12 => 'Décembre',
    ];

    #[Route('/preparer', name: 'app_admin_paie_preparer')]
    public function preparer(Request $request, PaieRepository $paieRepository): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        [$mois, $annee] = $this->resolvePeriod($request);

        $breakdown = $paieRepository->getServiceBreakdownByEntrepriseAndPeriod($entreprise, $mois, $annee);

        $totalEmployees = 0;
        $totalCalculated = 0;
        $totalPaid = 0;
        $totalMasse = 0.0;
        foreach ($breakdown as $row) {
            $totalEmployees += $row['total_employees'];
            $totalCalculated += $row['calculated'];
            $totalPaid += $row['paid'];
            $totalMasse += $row['masse'];
        }

        $pendingCount = $totalEmployees - $totalCalculated;

        return $this->render('admin/paie/preparer.html.twig', [
            'breakdown' => $breakdown,
            'mois' => $mois,
            'annee' => $annee,
            'moisLabel' => self::MOIS_LABELS[$mois] ?? $mois,
            'periods' => $this->buildPeriodOptions($paieRepository, $entreprise, $mois, $annee),
            'totalEmployees' => $totalEmployees,
            'totalCalculated' => $totalCalculated,
            'totalPaid' => $totalPaid,
            'totalMasse' => $totalMasse,
            'pendingCount' => $pendingCount,
        ]);
    }

    #[Route('/generer', name: 'app_admin_paie_generer')]
    public function generer(Request $request, PaieRepository $paieRepository): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        [$mois, $annee] = $this->resolvePeriod($request);

        $paies = $paieRepository->findByEntrepriseAndPeriod($entreprise, $mois, $annee);

        $totalBrut = 0.0;
        $totalCotisations = 0.0;
        $totalNet = 0.0;
        $totalPaid = 0;
        foreach ($paies as $paie) {
            $totalBrut += (float) $paie->getSalaireBrut();
            $totalCotisations += (float) $paie->getCotisations();
            $totalNet += (float) $paie->getSalaireNet();
            if ($paie->isPaid()) {
                $totalPaid++;
            }
        }

        return $this->render('admin/paie/generer.html.twig', [
            'paies' => $paies,
            'mois' => $mois,
            'annee' => $annee,
            'moisLabel' => self::MOIS_LABELS[$mois] ?? $mois,
            'periods' => $this->buildPeriodOptions($paieRepository, $entreprise, $mois, $annee),
            'totalBrut' => $totalBrut,
            'totalCotisations' => $totalCotisations,
            'totalNet' => $totalNet,
            'totalPaid' => $totalPaid,
            'totalCount' => count($paies),
        ]);
    }

    #[Route('/paie/calculer-tout', name: 'app_admin_paie_calculer_tout', methods: ['POST'])]
    public function calculerTout(
        Request $request,
        PaieRepository $paieRepository,
        PaieCalculatorService $calculator,
        PointageRepository $pointageRepository,
        AvanceSalaireRepository $avanceSalaireRepository,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('calculer-tout', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('app_admin_paie_preparer');
        }

        $entreprise = $this->getUser()->getEntreprise();
        $mois = $request->request->getInt('mois', (int) date('m'));
        $annee = $request->request->getInt('annee', (int) date('Y'));

        $employees = $paieRepository->findEmployeesWithoutPaieForPeriod($entreprise, $mois, $annee);

        $calculated = 0;
        foreach ($employees as $employee) {
            $this->calculatePayslipFor($employee, $mois, $annee, $calculator, $pointageRepository, $avanceSalaireRepository, $em);
            $calculated++;
        }

        if ($calculated > 0) {
            $em->flush();
            $this->addFlash('success', sprintf('%d fiche(s) de paie calculée(s) pour %s %d.', $calculated, self::MOIS_LABELS[$mois] ?? $mois, $annee));
        } else {
            $this->addFlash('info', 'Toutes les paies éligibles sont déjà calculées pour cette période.');
        }

        return $this->redirectToRoute('app_admin_paie_preparer', ['mois' => $mois, 'annee' => $annee]);
    }

    #[Route('/calculer/{id}', name: 'app_admin_paie_calculer', methods: ['POST'])]
    public function calculer(
        Request $request,
        User $employe,
        PaieCalculatorService $calculator,
        PointageRepository $pointageRepository,
        AvanceSalaireRepository $avanceSalaireRepository,
        EntityManagerInterface $em
    ): Response {
        if (!$this->isCsrfTokenValid('calculer-'.$employe->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('app_admin_paie_preparer');
        }

        if ($employe->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Cet employé n'appartient pas à votre entreprise.");
        }

        $mois = $request->request->getInt('mois', (int) date('m'));
        $annee = $request->request->getInt('annee', (int) date('Y'));

        if ($employe->getSalaireBase() === null) {
            $this->addFlash('danger', sprintf(
                'Impossible de calculer la paie de %s %s : aucun salaire de base défini pour cet employé.',
                $employe->getPrenom(),
                $employe->getNom()
            ));

            return $this->redirectToRoute('app_admin_paie_preparer', ['mois' => $mois, 'annee' => $annee]);
        }

        $this->calculatePayslipFor($employe, $mois, $annee, $calculator, $pointageRepository, $avanceSalaireRepository, $em);
        $em->flush();

        $this->addFlash('success', sprintf('Paie de %s %s calculée avec succès !', $employe->getPrenom(), $employe->getNom()));

        return $this->redirectToRoute('app_admin_paie_preparer', ['mois' => $mois, 'annee' => $annee]);
    }

    #[Route('/paie/{id}/valider', name: 'app_admin_paie_valider', methods: ['POST'])]
    public function valider(Request $request, Paie $paie, EntityManagerInterface $em, PayslipService $payslipService, AvanceSalaireRepository $avanceSalaireRepository): Response
    {
        if (!$this->isCsrfTokenValid('valider-'.$paie->getId(), (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('app_admin_paie_generer');
        }

        $this->assertOwnership($paie);

        $mois = $paie->getMois();
        $annee = $paie->getAnnee();

        if ($paie->isPaid()) {
            $this->addFlash('info', 'Cette fiche de paie a déjà été payée et envoyée.');

            return $this->redirectToRoute('app_admin_paie_generer', ['mois' => $mois, 'annee' => $annee]);
        }

        $this->payAndSend($paie, $payslipService, $avanceSalaireRepository, $em);
        $em->flush();

        $employee = $paie->getEmployee();
        if ($paie->getPayslipSentAt()) {
            $this->addFlash('success', sprintf(
                'Paie de %s %s validée, payée et fiche envoyée par email à %s.',
                $employee->getPrenom(),
                $employee->getNom(),
                $employee->getEmail()
            ));
        } else {
            $this->addFlash('warning', sprintf(
                'Paie de %s %s validée et payée, mais la fiche n\'a pas pu être envoyée par email (adresse email manquante ou erreur d\'envoi).',
                $employee->getPrenom(),
                $employee->getNom()
            ));
        }

        return $this->redirectToRoute('app_admin_paie_generer', ['mois' => $mois, 'annee' => $annee]);
    }

    #[Route('/paie/valider-selection', name: 'app_admin_paie_valider_selection', methods: ['POST'])]
    public function validerSelection(Request $request, EntityManagerInterface $em, PaieRepository $paieRepository, PayslipService $payslipService, AvanceSalaireRepository $avanceSalaireRepository): Response
    {
        if (!$this->isCsrfTokenValid('valider-selection', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');

            return $this->redirectToRoute('app_admin_paie_generer');
        }

        $mois = $request->request->getInt('mois', (int) date('m'));
        $annee = $request->request->getInt('annee', (int) date('Y'));

        $ids = array_filter(array_map('intval', $request->request->all('selected_paies')));
        $validateAll = $request->request->getBoolean('all');

        $entreprise = $this->getUser()->getEntreprise();

        if ($validateAll) {
            $candidates = array_filter(
                $paieRepository->findByEntrepriseAndPeriod($entreprise, $mois, $annee),
                static fn (Paie $p) => !$p->isPaid()
            );
        } elseif (!empty($ids)) {
            $candidates = array_filter(
                array_map(fn (int $id) => $paieRepository->find($id), $ids),
                function (?Paie $p) use ($entreprise) {
                    return $p !== null
                        && !$p->isPaid()
                        && $p->getEmployee()?->getEntreprise() === $entreprise;
                }
            );
        } else {
            $this->addFlash('warning', 'Veuillez sélectionner au moins une fiche de paie à valider.');

            return $this->redirectToRoute('app_admin_paie_generer', ['mois' => $mois, 'annee' => $annee]);
        }

        $success = 0;
        $sent = 0;
        foreach ($candidates as $paie) {
            $this->payAndSend($paie, $payslipService, $avanceSalaireRepository, $em);
            $success++;
            if ($paie->getPayslipSentAt()) {
                $sent++;
            }
        }

        if ($success > 0) {
            $em->flush();
            $this->addFlash('success', sprintf(
                '%d fiche(s) validée(s) et payée(s), dont %d envoyée(s) automatiquement par email.',
                $success,
                $sent
            ));
        } else {
            $this->addFlash('info', 'Aucune fiche éligible à valider dans la sélection.');
        }

        return $this->redirectToRoute('app_admin_paie_generer', ['mois' => $mois, 'annee' => $annee]);
    }

    #[Route('/paie/{id}/voir', name: 'app_admin_paie_voir', methods: ['GET'])]
    public function voir(Paie $paie): Response
    {
        $this->assertOwnership($paie);

        return $this->render('admin/paie/voir.html.twig', [
            'payslip' => $paie,
            'employee' => $paie->getEmployee(),
            'entreprise' => $paie->getEmployee()?->getEntreprise(),
        ]);
    }

    #[Route('/paie/{id}/telecharger', name: 'app_admin_paie_telecharger', methods: ['GET'])]
    public function telecharger(Paie $paie, PayslipService $payslipService): Response
    {
        $this->assertOwnership($paie);

        $pdfContent = $payslipService->generatePdf($paie);

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$payslipService->buildFilename($paie).'"',
        ]);
    }

    #[Route('/paie/exporter', name: 'app_admin_paie_exporter', methods: ['GET'])]
    public function exporter(Request $request, PaieRepository $paieRepository): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        [$mois, $annee] = $this->resolvePeriod($request);

        $paies = $paieRepository->findByEntrepriseAndPeriod($entreprise, $mois, $annee);

        $lines = ["Employé;Poste;Service;Salaire Brut;Cotisations;Salaire Net;Statut;Payée le;Fiche envoyée le;Validée par"];
        foreach ($paies as $paie) {
            $employee = $paie->getEmployee();
            $validePar = $paie->getValidePar();
            $lines[] = implode(';', [
                sprintf('%s %s', $employee->getPrenom(), $employee->getNom()),
                $employee->getPoste() ?? '-',
                $employee->getService() ?? '-',
                $paie->getSalaireBrut(),
                $paie->getCotisations(),
                $paie->getSalaireNet(),
                $paie->getStatus(),
                $paie->getPaidAt()?->format('d/m/Y H:i') ?? '-',
                $paie->getPayslipSentAt()?->format('d/m/Y H:i') ?? '-',
                $validePar ? sprintf('%s %s', $validePar->getPrenom(), $validePar->getNom()) : '-',
            ]);
        }

        $filename = sprintf('paie-%02d-%d.csv', $mois, $annee);
        $csv = "\xEF\xBB\xBF".implode("\n", $lines); // BOM UTF-8 pour Excel

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Calcule (ou recalcule si non payée) la fiche de paie d'un employé pour une période
     * et la persiste sans flush (le flush est laissé à l'appelant).
     */
    private function calculatePayslipFor(
        User $employee,
        int $mois,
        int $annee,
        PaieCalculatorService $calculator,
        PointageRepository $pointageRepository,
        AvanceSalaireRepository $avanceSalaireRepository,
        EntityManagerInterface $em
    ): Paie {
        $joursAbsents = $pointageRepository->countAbsencesByEmployeAndMonth($employee, $mois, $annee);

        $paie = $em->getRepository(Paie::class)->findOneBy([
            'employee' => $employee,
            'mois' => $mois,
            'annee' => $annee,
        ]);

        if (!$paie) {
            $paie = new Paie();
            $paie->setEmployee($employee)->setMois($mois)->setAnnee($annee);
        }

        // On ne recalcule jamais une paie déjà payée, pour ne pas écraser un montant versé.
        if ($paie->isPaid()) {
            return $paie;
        }

        // Avances déjà versées à l'employé (statut "payee") et pas encore récupérées sur
        // salaire : elles doivent être déduites du salaire net de cette paie. On inclut aussi
        // celles déjà rattachées à CETTE fiche de paie (cas d'un recalcul avant paiement final),
        // pour ne jamais les compter deux fois ni les ignorer entre deux recalculs.
        $avancesADeduire = $avanceSalaireRepository->findDeductiblesByEmployee($employee, $paie->getId() ? $paie : null);
        $montantAvances = array_sum(array_map(static fn (AvanceSalaire $a) => $a->getMontant(), $avancesADeduire));

        // Heures sup réellement pointées ce mois-ci, réparties entre le seuil majoré à
        // 30% et le reste à 50% (cf. PaieCalculatorService) : jusqu'ici ces heures
        // étaient calculées et affichées côté reporting RH mais jamais réellement payées.
        $heuresSupMois = $pointageRepository->sumOvertimeHoursByEmployeAndMonth($employee, $mois, $annee);
        $hs30 = min($heuresSupMois, PaieCalculatorService::SEUIL_HS_30_PAR_MOIS);
        $hs50 = max(0, $heuresSupMois - PaieCalculatorService::SEUIL_HS_30_PAR_MOIS);

        $dataInput = [
            'salaire_base' => $employee->getSalaireBase(),
            'jours_absents' => $joursAbsents,
            'jours_presents' => $pointageRepository->countPresentDaysByEmployeAndMonth($employee, $mois, $annee),
            'anciennete_annees' => $employee->getAncienneteAnnees() ?? 0,
            'personnes_a_charge' => $employee->getPersonnesACharge() ?? 0,
            'avance_a_deduire' => $montantAvances,
            'heures_contractuelles' => $employee->getHeuresContractuelles() ?? 8.0,
            'hs_30' => $hs30,
            'hs_50' => $hs50,
        ];

        $resultatPaie = $calculator->calculerPaie($dataInput);

        $paie->setSalaireBrut(sprintf('%.2f', $resultatPaie['salaire_brut']))
            ->setCotisations(sprintf('%.2f', $resultatPaie['cnaps'] + $resultatPaie['ostie'] + $resultatPaie['irsa']))
            ->setSalaireNet(sprintf('%.2f', $resultatPaie['salaire_net']))
            ->setMontantAvanceDeduite($resultatPaie['avance_deduite'])
            ->setMontantHeuresSupplementaires($resultatPaie['montant_hs'])
            ->setMasseSalariale($resultatPaie['salaire_brut'])
            ->setStatus('calculée');

        $em->persist($paie);

        // Rattache (sans encore les marquer remboursées) les avances prises en compte dans ce
        // calcul, pour qu'elles ne soient pas comptées une deuxième fois par une autre paie
        // et pour pouvoir les faire basculer au statut "rembourse" au moment du paiement réel.
        foreach ($avancesADeduire as $avance) {
            $avance->setPaie($paie);
        }

        return $paie;
    }

    /**
     * Marque la fiche comme validée/payée puis déclenche l'envoi automatique et sécurisé
     * de la fiche de paie par email. En cas d'échec d'envoi, le paiement reste enregistré.
     */
    private function payAndSend(Paie $paie, PayslipService $payslipService, AvanceSalaireRepository $avanceSalaireRepository, EntityManagerInterface $em): void
    {
        $paie->setStatus('payée');
        $paie->setPaidAt(new \DateTimeImmutable());
        $paie->setValidePar($this->getUser());

        // Le versement effectif du salaire est le moment où les avances déduites sur cette
        // fiche sont réellement "remboursées" : elles ne comptent plus dans l'encours de
        // l'employé et ne pourront plus être déduites une seconde fois sur une autre paie.
        foreach ($avanceSalaireRepository->findByPaie($paie) as $avance) {
            $avance->setStatut(AvanceSalaire::STATUS_REMBOURSE);
            $avance->setDateRemboursement(new \DateTimeImmutable());
        }

        try {
            if ($payslipService->sendPayslipByEmail($paie)) {
                $paie->setPayslipSentAt(new \DateTimeImmutable());
            }
        } catch (\Throwable $e) {
            // L'échec d'envoi n'annule pas le paiement ; l'administrateur est informé dans le flash message.
        }

        $em->persist($paie);
    }

    private function assertOwnership(Paie $paie): void
    {
        if ($paie->getEmployee()?->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Vous n'êtes pas autorisé à accéder à cette fiche de paie.");
        }
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function resolvePeriod(Request $request): array
    {
        $mois = $request->query->getInt('mois', (int) date('m'));
        $annee = $request->query->getInt('annee', (int) date('Y'));

        if ($mois < 1 || $mois > 12) {
            $mois = (int) date('m');
        }

        return [$mois, $annee];
    }

    /**
     * Construit la liste des périodes disponibles (existantes + mois courant) pour le sélecteur.
     *
     * @return array<int, array{mois: int, annee: int, label: string, current: bool}>
     */
    private function buildPeriodOptions(PaieRepository $paieRepository, $entreprise, int $mois, int $annee): array
    {
        $periods = $paieRepository->getAvailablePeriods($entreprise);

        $options = [];
        $seen = [];

        $currentKey = sprintf('%d-%02d', (int) date('Y'), (int) date('m'));
        $options[$currentKey] = [
            'mois' => (int) date('m'),
            'annee' => (int) date('Y'),
        ];
        $seen[$currentKey] = true;

        foreach ($periods as $p) {
            $key = sprintf('%d-%02d', $p['annee'], $p['mois']);
            if (!isset($seen[$key])) {
                $options[$key] = ['mois' => $p['mois'], 'annee' => $p['annee']];
                $seen[$key] = true;
            }
        }

        $selectedKey = sprintf('%d-%02d', $annee, $mois);
        if (!isset($seen[$selectedKey])) {
            $options[$selectedKey] = ['mois' => $mois, 'annee' => $annee];
        }

        krsort($options);

        $result = [];
        foreach ($options as $key => $opt) {
            $result[] = [
                'mois' => $opt['mois'],
                'annee' => $opt['annee'],
                'label' => (self::MOIS_LABELS[$opt['mois']] ?? $opt['mois']).' '.$opt['annee'],
                'current' => $opt['mois'] === $mois && $opt['annee'] === $annee,
            ];
        }

        return $result;
    }
}

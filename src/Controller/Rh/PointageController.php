<?php

namespace App\Controller\Rh;

use App\Entity\Pointage;
use App\Entity\User;
use App\Repository\PointageRepository;
use App\Repository\UserRepository;
use App\Repository\PlanningRepository;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class PointageController extends AbstractController
{
    #[Route('/pointage', name: 'app_rh_pointage')]
    public function index(
        Request $request,
        PointageRepository $pointageRepo,
        UserRepository $userRepo,
        PaginatorInterface $paginator,
        \App\Service\PlanningService $planningService
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $date = new \DateTimeImmutable($request->query->get('date', 'today'));
        $status = $request->query->get('status');
        $service = $request->query->get('service');
        $q = $request->query->get('q');

        $query = $pointageRepo->findByDateWithFilters($date, $status, $service, $q, $entreprise);

        $pointages = $paginator->paginate($query, $request->query->getInt('page', 1), 20);
        $stats = $pointageRepo->getStatsByDate($date, $entreprise);
        $isValide = $pointageRepo->isDateValide($date, $entreprise);
        $services = $userRepo->findDistinctServices($entreprise);
        $todayData = $planningService->getTodayData($this->getUser());

        return $this->render('rh/pointage/index.html.twig', [
            'pointages' => $pointages,
            'stats' => $stats,
            'date' => $date,
            'isValide' => $isValide,
            'services' => $services,
            'status' => $status,
            'service' => $service,
            'q' => $q,
            'monPointage' => $todayData['pointage'],
            'planning' => $todayData['planning'],
            'canPointerEntree' => $todayData['canPointerEntree'],
            'canPointerSortie' => $todayData['canPointerSortie'],
            'lateDeadlinePassed' => $todayData['lateDeadlinePassed'],
            'heureLimitePointage' => $todayData['heureLimitePointage'],
            'message' => $todayData['message'],
        ]);
    }

    /**
     * Le RH démarre son propre shift (pointage personnel) afin d'être payé comme
     * n'importe quel employé : crée le pointage du jour si besoin et fixe l'heure d'entrée.
     */
    #[Route('/pointage/mon-pointage/debut', name: 'app_rh_pointage_debut', methods: ['POST'])]
    public function demarrerShift(Request $request, \App\Service\PlanningService $planningService): Response
    {
        if (!$this->isCsrfTokenValid('mon_pointage_debut', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_dashboard');
        }

        try {
            $planningService->pointerEntree($this->getUser());
            $this->addFlash('success', 'Shift démarré selon l’horaire prévu par votre planning.');
        } catch (\RuntimeException $e) {
            $this->addFlash('danger', $e->getMessage());
        }
        return $this->redirectBackOrDashboard($request);
    }

    /**
     * Le RH termine son propre shift : fixe l'heure de sortie sur le pointage du jour.
     */
    #[Route('/pointage/mon-pointage/fin', name: 'app_rh_pointage_fin', methods: ['POST'])]
    public function terminerShift(Request $request, PointageRepository $pointageRepo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('mon_pointage_fin', (string) $request->request->get('_token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_dashboard');
        }

        $user = $this->getUser();
        $entreprise = $user->getEntreprise();
        $today = new \DateTimeImmutable('today');

        $pointage = $pointageRepo->findOneBy(['employee' => $user, 'date' => $today, 'entreprise' => $entreprise]);

        if (!$pointage || !$pointage->getHeureEntree()) {
            $this->addFlash('danger', 'Vous devez d\'abord démarrer votre shift avant de le terminer.');
            return $this->redirectBackOrDashboard($request);
        }

        if ($pointage->isValide()) {
            $this->addFlash('danger', 'Ce pointage a déjà été validé, il ne peut plus être modifié.');
            return $this->redirectBackOrDashboard($request);
        }

        if ($pointage->getHeureSortie()) {
            $this->addFlash('info', 'Votre shift est déjà terminé aujourd\'hui à '.$pointage->getHeureSortie()->format('H:i').'.');
            return $this->redirectBackOrDashboard($request);
        }

        $pointage->setHeureSortie(new \DateTimeImmutable());

        $em->flush();

        $this->addFlash('success', 'Shift terminé à '.$pointage->getHeureSortie()->format('H:i').'. '.$pointage->getFormattedHeuresTravaillees().' travaillées.');
        return $this->redirectBackOrDashboard($request);
    }

    private function findOrBuildMonPointage(PointageRepository $pointageRepo): ?Pointage
    {
        $user = $this->getUser();
        $entreprise = $user->getEntreprise();
        $today = new \DateTimeImmutable('today');

        return $pointageRepo->findOneBy(['employee' => $user, 'date' => $today, 'entreprise' => $entreprise]);
    }

    private function redirectBackOrDashboard(Request $request): Response
    {
        $referer = $request->headers->get('referer');
        if ($referer) {
            return $this->redirect($referer);
        }
        return $this->redirectToRoute('app_rh_dashboard');
    }

    #[Route('/pointage/corriger/{id}', name: 'app_rh_pointage_corriger', methods: ['POST'])]
    public function corriger(Request $request, Pointage $pointage, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('corriger_pointage_'.$pointage->getId(), (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_pointage', ['date' => $request->request->get('date')]);
        }

        if ($pointage->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException("Ce pointage n'appartient pas à votre entreprise.");
        }

        if($pointage->isValide()) {
            $this->addFlash('danger', 'Journée déjà validée. Impossible de modifier.');
            return $this->redirectToRoute('app_rh_pointage', ['date' => $request->request->get('date')]);
        }

        $heureEntree = $request->request->get('heureEntree'); // format H:i
        $heureSortie = $request->request->get('heureSortie');
        $date = $pointage->getDate()->format('Y-m-d');

        if($heureEntree) {
            $pointage->setHeureEntree(\DateTimeImmutable::createFromFormat('Y-m-d H:i', $date.' '.$heureEntree));
        }
        if($heureSortie) {
            $pointage->setHeureSortie(\DateTimeImmutable::createFromFormat('Y-m-d H:i', $date.' '.$heureSortie));
        }

        $pointage->setMotifCorrection($request->request->get('motif'));
        $pointage->setCorrigePar($this->getUser());
        $pointage->setCorrigeLe(new \DateTimeImmutable()); // <-- Immutable
        $pointage->setStatut($pointage->getMinutesRetard() > 0 ? 'retard' : 'present'); // auto

        $em->flush();
        $this->addFlash('success', 'Pointage corrigé avec succès');

        return $this->redirectToRoute('app_rh_pointage', ['date' => $date]);
    }

    #[Route('/pointage/valider/{date}', name: 'app_rh_pointage_valider', methods: ['POST'])]
    public function valider(Request $request, \DateTimeImmutable $date, PointageRepository $repo, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('valider_pointage_'.$date->format('Y-m-d'), (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_pointage', ['date' => $date->format('Y-m-d')]);
        }

        $entreprise = $this->getUser()->getEntreprise();
        $repo->validerJournee($date, $entreprise); // <-- passe l'entreprise

        $this->addFlash('success', 'Journée du '.$date->format('d/m/Y').' validée');
        return $this->redirectToRoute('app_rh_pointage', ['date' => $date->format('Y-m-d')]);
    }


    #[Route('/pointage/export', name: 'app_rh_pointage_export', methods: ['POST', 'GET'])]
    public function export(Request $request, PointageRepository $pointageRepo): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $date = new \DateTimeImmutable($request->query->get('date', $request->request->get('date', 'today')));
        $status = $request->query->get('status', $request->request->get('status'));
        $service = $request->query->get('service', $request->request->get('service'));
        $q = $request->query->get('q', $request->request->get('q'));

        $pointages = $pointageRepo->findByDateWithFilters($date, $status, $service, $q, $entreprise)->getQuery()->getResult();

        $lines = ["Employé;Poste;Service;Statut;Heure prévue début;Heure prévue fin;Heure entrée;Heure sortie;Heures travaillées;Retard (min)"];
        foreach ($pointages as $pointage) {
            $employee = $pointage->getEmployee();
            $lines[] = implode(';', [
                sprintf('%s %s', $employee->getPrenom(), $employee->getNom()),
                $employee->getPoste() ?? '-',
                $employee->getService() ?? '-',
                $pointage->getStatutLabel(),
                $pointage->getHeurePrevueDebut()?->format('H:i') ?? '-',
                $pointage->getHeurePrevueFin()?->format('H:i') ?? '-',
                $pointage->getHeureEntree()?->format('H:i') ?? '-',
                $pointage->getHeureSortie()?->format('H:i') ?? '-',
                $pointage->getFormattedHeuresTravaillees(),
                $pointage->getMinutesRetard(),
            ]);
        }

        $filename = sprintf('pointage-%s.csv', $date->format('Y-m-d'));
        $csv = "\xEF\xBB\xBF".implode("\n", $lines); // BOM UTF-8 pour Excel

        return new Response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    #[Route('/pointage/generer', name: 'app_rh_pointage_generer', methods: ['POST'])]
    public function generer(Request $request, EntityManagerInterface $em, PlanningRepository $planningRepo, PointageRepository $pointageRepo): Response
    {
        if (!$this->isCsrfTokenValid('generer_pointage', (string) $request->request->get('token'))) {
            $this->addFlash('danger', 'Jeton de sécurité invalide. Merci de réessayer.');
            return $this->redirectToRoute('app_rh_pointage');
        }

        $date = new \DateTimeImmutable($request->request->get('date', 'today'));
        $entreprise = $this->getUser()->getEntreprise();

        $weekStart = $date->modify('monday this week');
        $jours = ['monday'=>'lundi', 'tuesday'=>'mardi', 'wednesday'=>'mercredi', 'thursday'=>'jeudi', 'friday'=>'vendredi', 'saturday'=>'samedi', 'sunday'=>'dimanche'];
        $dayOfWeekFr = $jours[strtolower($date->format('l'))];

        $plannings = $planningRepo->findBy([
            'weekStart' => $weekStart,
            'dayOfWeek' => $dayOfWeekFr,
            'entreprise' => $entreprise,
            'status' => 'valide'
        ]);

        $created = 0;
        foreach($plannings as $p) {
            if($pointageRepo->findOneBy(['employee' => $p->getUser(), 'date' => $date, 'entreprise' => $entreprise])) continue;
            if(!$p->isTravail() || !$p->getHeureDebut() || !$p->getHeureFin()) continue;

            $pointage = new Pointage();
            $pointage->setEmployee($p->getUser());
            $pointage->setEntreprise($entreprise);
            $pointage->setDate($date);
            $pointage->setHeurePrevueDebut($p->getHeureDebut() ? \DateTimeImmutable::createFromInterface($p->getHeureDebut()) : null);
            $pointage->setHeurePrevueFin($p->getHeureFin() ? \DateTimeImmutable::createFromInterface($p->getHeureFin()) : null);
            // La génération crée un registre absent ; le statut présent ne vient
            // qu’après un véritable pointage d’entrée.
            $pointage->setStatut('absent');

            $em->persist($pointage);
            $created++;
        }
        $em->flush();
        $this->addFlash('success', "$created pointages générés pour le ".$date->format('d/m/Y'));
        return $this->redirectToRoute('app_rh_pointage', ['date' => $date->format('Y-m-d')]);
    }
}

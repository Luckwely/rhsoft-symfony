<?php

namespace App\Controller\Admin;

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

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class PointageController extends AbstractController
{
    #[Route('/pointage', name: 'app_admin_pointage')]
    public function index(
        Request $request,
        PointageRepository $pointageRepo,
        UserRepository $userRepo,
        PaginatorInterface $paginator
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

        return $this->render('admin/pointage/index.html.twig', [
            'pointages' => $pointages,
            'stats' => $stats,
            'date' => $date,
            'isValide' => $isValide,
            'services' => $services,
            'status' => $status,
            'service' => $service,
            'q' => $q,
        ]);
    }

    #[Route('/pointage/corriger/{id}', name: 'app_admin_pointage_corriger', methods: ['POST'])]
    public function corriger(
        Request $request,
        Pointage $pointage,
        EntityManagerInterface $em
    ): Response
    {
        if ($pointage->getEntreprise() !== $this->getUser()->getEntreprise()) {
            throw $this->createAccessDeniedException('Pointage hors de votre entreprise.');
        }
        if (!$this->isCsrfTokenValid('admin_pointage_corriger_'.$pointage->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        if($pointage->isValide()) {
            $this->addFlash('danger', 'Journée déjà validée. Impossible de modifier.');
            return $this->redirectToRoute('app_admin_pointage', ['date' => $request->request->get('date')]);
        }

        $heureEntree = $request->request->get('heureEntree');
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
        $pointage->setCorrigeLe(new \DateTimeImmutable());
        $pointage->setStatut($pointage->getStatutEffectif());

        $em->flush();
        $this->addFlash('success', 'Pointage corrigé avec succès');

        return $this->redirectToRoute('app_admin_pointage', ['date' => $date]);
    }

    #[Route('/pointage/valider/{date}', name: 'app_admin_pointage_valider', methods: ['POST'])]
    public function valider(
        \DateTimeImmutable $date,
        Request $request,
        PointageRepository $repo,
        EntityManagerInterface $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('admin_pointage_valider_'.$date->format('Y-m-d'), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        $entreprise = $this->getUser()->getEntreprise();
        $repo->validerJournee($date, $entreprise);

        $this->addFlash('success', 'Journée du '.$date->format('d/m/Y').' validée');
        return $this->redirectToRoute('app_admin_pointage', ['date' => $date->format('Y-m-d')]);
    }


    #[Route('/pointage/export', name: 'app_admin_pointage_export', methods: ['POST', 'GET'])]
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
                $pointage->getStatutEffectifLabel(),
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

    #[Route('/pointage/generer', name: 'app_admin_pointage_generer', methods: ['POST'])]
    public function generer(
        Request $request,
        EntityManagerInterface $em,
        PlanningRepository $planningRepo,
        PointageRepository $pointageRepo
    ): Response
    {
        if (!$this->isCsrfTokenValid('admin_pointage_generer', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton de sécurité invalide.');
        }
        $date = new \DateTimeImmutable($request->request->get('date', 'today'));
        $entreprise = $this->getUser()->getEntreprise();

        $weekStart = $date->modify('monday this week');
        $jours = [
            'monday'=>'lundi',
            'tuesday'=>'mardi',
            'wednesday'=>'mercredi',
            'thursday'=>'jeudi',
            'friday'=>'vendredi',
            'saturday'=>'samedi',
            'sunday'=>'dimanche'
        ];
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
            if($p->isDayOff()) continue;

            $pointage = new Pointage();
            $pointage->setEmployee($p->getUser());
            $pointage->setEntreprise($entreprise);
            $pointage->setDate($date);
            $pointage->setHeurePrevueDebut($p->getHeureDebut() ? \DateTimeImmutable::createFromInterface($p->getHeureDebut()) : null);
            $pointage->setHeurePrevueFin($p->getHeureFin() ? \DateTimeImmutable::createFromInterface($p->getHeureFin()) : null);
           
            $pointage->setStatut('present');

            $em->persist($pointage);
            $created++;
        }
        $em->flush();
        $this->addFlash('success', "$created pointages générés pour le ".$date->format('d/m/Y'));
        return $this->redirectToRoute('app_admin_pointage', [
            'date' => $date->format('Y-m-d')
        ]);
    }
}

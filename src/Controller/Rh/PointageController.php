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

        return $this->render('rh/pointage/index.html.twig', [
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

    #[Route('/pointage/corriger/{id}', name: 'app_rh_pointage_corriger', methods: ['POST'])]
    public function corriger(Request $request, Pointage $pointage, EntityManagerInterface $em): Response
    {
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

    #[Route('/pointage/valider/{date}', name: 'app_rh_pointage_valider')]
    public function valider(\DateTimeImmutable $date, PointageRepository $repo, EntityManagerInterface $em): Response // <-- Immutable
    {
        $entreprise = $this->getUser()->getEntreprise();
        $repo->validerJournee($date, $entreprise); // <-- passe l'entreprise

        $this->addFlash('success', 'Journée du '.$date->format('d/m/Y').' validée');
        return $this->redirectToRoute('app_rh_pointage', ['date' => $date->format('Y-m-d')]);
    }


    #[Route('/pointage/export', name: 'app_rh_pointage_export', methods: ['POST'])]
    public function export(Request $request): Response
    {
        $this->addFlash('info', 'Export en cours...');
        return $this->redirectToRoute('app_rh_pointage');
    }

    #[Route('/pointage/generer', name: 'app_rh_pointage_generer', methods: ['POST'])]
    public function generer(Request $request, EntityManagerInterface $em, PlanningRepository $planningRepo, PointageRepository $pointageRepo): Response
    {
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
            if($p->isDayOff()) continue;

            $pointage = new Pointage();
            $pointage->setEmployee($p->getUser());
            $pointage->setEntreprise($entreprise);
            $pointage->setDate($date);
            $pointage->setHeurePrevueDebut($p->getHeureDebut() ? \DateTimeImmutable::createFromInterface($p->getHeureDebut()) : null);
            $pointage->setHeurePrevueFin($p->getHeureFin() ? \DateTimeImmutable::createFromInterface($p->getHeureFin()) : null);
            $pointage->setPausePrevueMinutes($p->getPauseMinutes());
            $pointage->setPauseMinutes($p->getPauseMinutes());
            $pointage->setStatut('present');

            $em->persist($pointage);
            $created++;
        }
        $em->flush();
        $this->addFlash('success', "$created pointages générés pour le ".$date->format('d/m/Y'));
        return $this->redirectToRoute('app_rh_pointage', ['date' => $date->format('Y-m-d')]);
    }
}

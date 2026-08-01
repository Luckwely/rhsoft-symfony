<?php

namespace App\Controller\Rh;

use App\Entity\Planning;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Knp\Component\Pager\PaginatorInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class PlanningController extends AbstractController
{
    private array $days = [
        'lundi',
        'mardi',
        'mercredi',
        'jeudi',
        'vendredi',
        'samedi',
        'dimanche'
    ];


    #[Route('/planning', name: 'app_rh_planning')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $today = new \DateTime('today');
        $mondayThisWeek = new \DateTime('monday this week');
        $mondayThisWeek->setTime(0,0,0);

        $weekParam = $request->query->get('week');
        $weekStart = $weekParam? new \DateTime($weekParam) : clone $mondayThisWeek;
        $weekStart->setTime(0,0,0);

        $minDate = (clone $mondayThisWeek)->modify('-4 weeks');
        if($weekStart < $minDate) $weekStart = clone $minDate;

        $canEdit = $weekStart >= $mondayThisWeek;

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true)
            ->orderBy('u.nom', 'ASC');

        // FILTRE RECHERCHE
        if ($search = $request->query->get('q')) {
            $queryBuilder
                ->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }

        // FILTRE SERVICE/ROLE
        if ($role = $request->query->get('role')) {
            $queryBuilder
                ->andWhere('u.roles LIKE :role')
                ->setParameter('role', '%"'.$role.'"%');
        }

        $employees = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);

        $plannings = $em->getRepository(Planning::class)->findBy([
            'entreprise' => $entreprise,
            'weekStart' => $weekStart
        ]);

        $planningMap = [];
        foreach($plannings as $p){
            $planningMap[$p->getUser()->getId()][$p->getDayOfWeek()] = $p;
        }

        $prevWeek = (clone $weekStart)->modify('-7 days');
        $nextWeek = (clone $weekStart)->modify('+7 days');

        return $this->render('rh/planning/index.html.twig', [
            'employees' => $employees,
            'planningMap' => $planningMap,
            'weekStart' => $weekStart,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'days' => $this->days,
            'canEdit' => $canEdit,
            'search' => $search?? '',
            'role' => $role?? ''
        ]);
    }

    #[Route('/planning/save', name: 'app_rh_planning_save', methods: ['POST'])]
    public function save(Request $request, EntityManagerInterface $em): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $weekStart = new \DateTime($request->request->get('week_start'));
        $weekStart->setTime(0,0,0);
        $mondayThisWeek = new \DateTime('monday this week');
        $mondayThisWeek->setTime(0,0,0);


        if($weekStart < $mondayThisWeek){
            $this->addFlash('danger', 'Impossible de modifier une semaine passée');
            return $this->redirectToRoute('app_rh_planning', ['week' => $weekStart->format('Y-m-d')]);
        }

        $postData = $request->request->all('planning');

        foreach($postData as $userId => $daysData){
            $user = $em->getRepository(User::class)->find($userId);
            if(!$user || $user->getEntreprise() !== $entreprise) continue;

            foreach($this->days as $day){
                $dayData = $daysData[$day]?? [];

                $planning = $em->getRepository(Planning::class)->findOneBy([
                    'user' => $user,
                    'weekStart' => $weekStart,
                    'dayOfWeek' => $day
                ]);

                if($planning && $planning->getStatus() === 'valide'){
                    $newPlanning = new Planning();
                    $newPlanning->setUser($user);
                    $newPlanning->setEntreprise($entreprise);
                    $newPlanning->setWeekStart($weekStart);
                    $newPlanning->setDayOfWeek($day);
                    $newPlanning->setStatus('brouillon');
                    $planning = $newPlanning;
                }

                if(!$planning){
                    $planning = new Planning();
                    $planning->setUser($user);
                    $planning->setEntreprise($entreprise);
                    $planning->setWeekStart($weekStart);
                    $planning->setDayOfWeek($day);
                }

                $planning->setDayOff(isset($dayData['isDayOff']) && $dayData['isDayOff'] == '1');

                if($planning->isDayOff()){
                    $planning->setHeureDebut(null);
                    $planning->setHeureFin(null);
                } else {
                    $planning->setHeureDebut(!empty($dayData['heureDebut'])? \DateTime::createFromFormat('H:i', $dayData['heureDebut']) : null);
                    $planning->setHeureFin(!empty($dayData['heureFin'])? \DateTime::createFromFormat('H:i', $dayData['heureFin']) : null);
                }

                $planning->setPauseMinutes($dayData['pauseMinutes']?? null);
                $planning->setComment($dayData['comment']?? null);

                $em->persist($planning);
            }
        }

        $em->flush();
        $this->addFlash('success', 'Planning de la semaine enregistré');
        return $this->redirectToRoute('app_rh_planning', ['week' => $weekStart->format('Y-m-d')]);
    }
}

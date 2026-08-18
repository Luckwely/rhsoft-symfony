<?php namespace App\Controller\Admin;

use App\Entity\Planning;
use App\Entity\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Knp\Component\Pager\PaginatorInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class PlanningController extends AbstractController
{
    private function getWeekDays(\DateTimeImmutable $weekStart): array
    {
        $days = [];
        for ($i = 0; $i < 7; $i++) {
            $days[] = $weekStart->modify("+$i days");
        }
        return $days;
    }

    #[Route('/planning', name: 'app_admin_planning')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        PaginatorInterface $paginator
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $mondayThisWeek = (new \DateTimeImmutable('monday this week'))->setTime(0, 0, 0);

        $weekParam = $request->query->get('week');
        $weekStart = $weekParam ? (new \DateTimeImmutable($weekParam))->setTime(0, 0, 0) : $mondayThisWeek;

        $minDate = $mondayThisWeek->modify('-4 weeks');
        if ($weekStart < $minDate) {
            $weekStart = $minDate;
        }
        $canEdit = $weekStart >= $mondayThisWeek;

        $queryBuilder = $em->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true)
            ->orderBy('u.nom', 'ASC');

        if ($search = $request->query->get('q')) {
            $queryBuilder->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%'.$search.'%');
        }
        if ($role = $request->query->get('role')) {
            $queryBuilder->andWhere('u.roles LIKE :role')->setParameter('role', '%"'.$role.'"%');
        }

        $employees = $paginator->paginate($queryBuilder, $request->query->getInt('page', 1), 10);

        $days = $this->getWeekDays($weekStart);
        $dayNames = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi', 'dimanche'];

        $plannings = $em->getRepository(Planning::class)->findBy([
            'entreprise' => $entreprise,
            'weekStart' => $weekStart
        ]);

        $planningMap = [];
        foreach ($plannings as $p) {
            $planningMap[$p->getUser()->getId()][$p->getDayOfWeek()] = $p;
        }

        $prevWeek = $weekStart->modify('-7 days');
        $nextWeek = $weekStart->modify('+7 days');

        return $this->render('admin/planning/index.html.twig', [
            'employees' => $employees,
            'planningMap' => $planningMap,
            'weekStart' => $weekStart,
            'prevWeek' => $prevWeek,
            'nextWeek' => $nextWeek,
            'days' => $days,
            'dayNames' => $dayNames,
            'canEdit' => $canEdit,
            'search' => $search ?? '',
            'role' => $role ?? ''
        ]);
    }

    #[Route('/planning/save', name: 'app_admin_planning_save', methods: ['POST'])]
    public function save(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $entreprise = $this->getUser()->getEntreprise();
        $weekParam = $request->request->get('week_start');
        $weekStart = $weekParam ? (new \DateTimeImmutable($weekParam))->setTime(0, 0, 0) : (new \DateTimeImmutable('monday this week'))->setTime(0, 0, 0);
        $mondayThisWeek = (new \DateTimeImmutable('monday this week'))->setTime(0, 0, 0);

        if ($weekStart < $mondayThisWeek) {
            $this->addFlash('danger', 'Impossible de modifier une semaine passée');
            return $this->redirectToRoute('app_admin_planning', ['week' => $weekStart->format('Y-m-d')]);
        }

        $dayNames = [
            'lundi',
            'mardi',
            'mercredi',
            'jeudi',
            'vendredi',
            'samedi',
            'dimanche'
        ];
        $postData = $request->request->all('planning');

        foreach ($postData as $userId => $daysData) {
            $user = $em->getRepository(User::class)->find($userId);
            if (!$user || $user->getEntreprise() !== $entreprise) {
                continue;
            }

            foreach ($dayNames as $index => $dayName) {
                $dayData = $daysData[$index] ?? [];
                $planning = $em->getRepository(Planning::class)->findOneBy([
                    'user' => $user,
                    'weekStart' => $weekStart,
                    'dayOfWeek' => $dayName
                ]);

                $isNew = false;
                if ($planning && $planning->getStatus() === 'valide') {
                    $planning = new Planning();
                    $isNew = true;
                }
                if (!$planning) {
                    $planning = new Planning();
                    $isNew = true;
                }

                if ($isNew) {
                    $planning->setUser($user);
                    $planning->setEntreprise($entreprise);
                    $planning->setWeekStart($weekStart);
                    $planning->setDayOfWeek($dayName);
                    $planning->setStatus('brouillon');
                }

                $planning->setDayOff(isset($dayData['isDayOff']) && $dayData['isDayOff'] == '1');
                if ($planning->isDayOff()) {
                    $planning->setHeureDebut(null);
                    $planning->setHeureFin(null);
                } else {
                    $planning->setHeureDebut(!empty($dayData['heureDebut']) ? \DateTime::createFromFormat('H:i', $dayData['heureDebut']) : null);
                    $planning->setHeureFin(!empty($dayData['heureFin']) ? \DateTime::createFromFormat('H:i', $dayData['heureFin']) : null);
                }
                $planning->setPauseMinutes(!empty($dayData['pauseMinutes']) ? (int)$dayData['pauseMinutes'] : null);
                $planning->setComment($dayData['comment'] ?? null);

                $em->persist($planning);
            }
        }

        
        try {
            $em->flush();
            $this->addFlash('success', 'Planning de la semaine enregistré');
        } catch (\Exception $e) {
            $this->addFlash('danger', 'Erreur BDD: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_planning', ['week' => $weekStart->format('Y-m-d')]);
    }
}

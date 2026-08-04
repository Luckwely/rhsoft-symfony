<?php
namespace App\Controller\Employe;

use App\Entity\Pointage;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/employe')]
#[IsGranted('ROLE_EMPLOYE')]
final class PlanningController extends AbstractController
{
#[Route('/pointage', name: 'app_employe_planning')]
    public function index(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $entreprise = $user->getEntreprise();
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();
        $dayName = strtolower($today->format('l'));

        if(!$entreprise->isModulePointage()){
            $this->addFlash('danger', 'Le module pointage est désactivé');
            return $this->redirectToRoute('app_employe_profile');
        }

        // 2. Récupérer le planning du jour
        $weekStart = $today->modify('monday this week')->setTime(0,0,0);
        $planning = $em->getRepository(\App\Entity\Planning::class)->findOneBy([
            'user' => $user, 'weekStart' => $weekStart, 'dayOfWeek' => $dayName
        ]);

        $isRepos = $planning? $planning->isDayOff() : false;
        $heureDebut = $planning? $planning->getHeureDebut() : null;
        $tolerance = $entreprise->getToleranceRetard()?? 15;

        // 3. Récupérer le pointage du jour
        $pointage = $em->getRepository(Pointage::class)->findOneBy([
            'employee' => $user, 'date' => $today
        ]);

        $canPointerEntree = true;
        $canPointerSortie = false;
        $message = null;

        if($isRepos){
            $canPointerEntree = false;
            $canPointerSortie = false;
            $message = "Journée de repos selon le planning";
        }
        elseif($pointage && $pointage->getHeureEntree()){
            $canPointerEntree = false;
            if(!$pointage->getHeureSortie()){
                $canPointerSortie = true;
            } else {
                $message = "Pointage du jour terminé";
            }
        }

        // 4. Vérifier tolerance retard
        if($heureDebut && !$pointage){
            $heureDebutImmutable = \DateTimeImmutable::createFromMutable($heureDebut)->setDate(
                $today->format('Y'), $today->format('m'), $today->format('d')
            );
            $heureLimite = $heureDebutImmutable->modify("+$tolerance minutes");

            if($now > $heureLimite){
                $canPointerEntree = false;
                $message = "Heure d'entrée dépassée. Tolérance: $tolerance min";
            }
        }

        // 5. Récupérer l'historique et la liste des plannings pour la vue
        $historique = $em->getRepository(Pointage::class)->findBy(
            ['employee' => $user],
            ['date' => 'DESC'],
            10
        );

       $planningsSemaine = $em->getRepository(\App\Entity\Planning::class)->findBy([
            'user' => $user,
            'weekStart' => $weekStart
        ]);

        $order = [
            'lundi' => 1,
            'mardi' => 2,
            'mercredi' => 3,
            'jeudi' => 4,
            'vendredi' => 5,
            'samedi' => 6,
            'dimanche' => 7
        ];

        usort($planningsSemaine, function($a, $b) use ($order) {
            $dayA = strtolower($a->getDayOfWeek());
            $dayB = strtolower($b->getDayOfWeek());

            return ($order[$dayA] ?? 99) <=> ($order[$dayB] ?? 99);
        });

        $joursTravailles = 0;
        $joursConges = 0; // À adapter si vous avez un champ/statut congé dans votre planning
        $joursRepos = 0;
        $totalMinutes = 0;

        foreach ($planningsSemaine as $plan) {
            if ($plan->isDayOff()) {
                $joursRepos++;
            } else {
                $joursTravailles++;

                // Calculer les heures prévues pour ce jour (Heure de fin - Heure de début - Pause)
                if ($plan->getHeureDebut() && $plan->getHeureFin()) {
                    $debut = \DateTimeImmutable::createFromMutable($plan->getHeureDebut());
                    $fin = \DateTimeImmutable::createFromMutable($plan->getHeureFin());
                    $diffMinutes = ($fin->getTimestamp() - $debut->getTimestamp()) / 60;
                    $pause = $plan->getPauseMinutes() ?? 0;

                    $netMinutes = $diffMinutes - $pause;
                    if ($netMinutes > 0) {
                        $totalMinutes += $netMinutes;
                    }
                }
            }
        }

        $totalHeuresFormatted = sprintf('%dh %02dmin', floor($totalMinutes / 60), $totalMinutes % 60);
        // ---------------------------------

        return $this->render('employe/planning/index.html.twig', [
            'pointage' => $pointage,
            'planning' => $planning,
            'canPointerEntree' => $canPointerEntree,
            'canPointerSortie' => $canPointerSortie,
            'message' => $message,
            'heuresTravaillees' => $pointage? $this->calculHeures($pointage) : '0h 00min',
            'historique' => $historique,
            'planningsSemaine' => $planningsSemaine,
            'joursTravailles' => $joursTravailles,
            'joursConges' => $joursConges,
            'joursRepos' => $joursRepos,
            'totalHeuresSemaine' => $totalHeuresFormatted,
        ]);
    }

    #[Route('/pointage/entree', name: 'app_employe_pointage_entree', methods: ['POST'])]
    public function pointerEntree(Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('pointer_entree', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token invalide');
        }
        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();

        $pointage = $em->getRepository(Pointage::class)->findOneBy(['employee' => $user, 'date' => $today]);
        if(!$pointage){
            $pointage = new Pointage();
            $pointage->setEmployee($user);
            $pointage->setDate($today);
        }
        $pointage->setHeureEntree($now);
        $em->persist($pointage);
        $em->flush();

        $this->addFlash('success', 'Entrée pointée à '.$now->format('H:i'));
        return $this->redirectToRoute('app_employe_planning');
    }

    #[Route('/pointage/sortie', name: 'app_employe_pointage_sortie', methods: ['POST'])]
    public function pointerSortie(Request $request, EntityManagerInterface $em): Response // <- ajoute Request
    {
        if (!$this->isCsrfTokenValid('pointer_sortie', $request->request->get('_token'))) { // <- AJOUTE ÇA
            throw $this->createAccessDeniedException('Token invalide');
        }

        $user = $this->getUser();
        $today = new \DateTimeImmutable('today');
        $now = new \DateTimeImmutable();
        $pointage = $em->getRepository(Pointage::class)->findOneBy(['employee' => $user, 'date' => $today]);
        if($pointage && !$pointage->getHeureSortie()){
            $pointage->setHeureSortie($now);
            $em->flush();
            $this->addFlash('success', 'Sortie pointée à '.$now->format('H:i'));
        }
        return $this->redirectToRoute('app_employe_planning');
    }

    private function calculHeures(Pointage $pointage): string
    {
        $entree = $pointage->getHeureEntree();
        $sortie = $pointage->getHeureSortie() ?? new \DateTimeImmutable();

        if (!$entree) {
            return '0h 00min';
        }

        $interval = $entree->diff($sortie);
        return sprintf('%dh %02dmin', $interval->h, $interval->i);
    }
}

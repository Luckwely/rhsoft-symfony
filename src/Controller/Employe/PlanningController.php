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
        $dayName = strtolower($today->format('l')); // lundi

        // 1. Vérifier si module actif
        if(!$entreprise->isModulePointage()){
            $this->addFlash('danger', 'Le module pointage est désactivé par votre entreprise');
            return $this->redirectToRoute('app_employe_profile');
        }

        // 2. Récupérer le planning du jour
        $weekStart = $today->modify('monday this week')->setTime(0,0,0);
        $planning = $em->getRepository(\App\Entity\Planning::class)->findOneBy([
            'user' => $user, 'weekStart' => $weekStart, 'dayOfWeek' => $dayName
        ]);

        $isRepos = $planning? $planning->isDayOff() : false;
        $heureDebut = $planning? $planning->getHeureDebut() : null; // <- c'est un DateTime mutable
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
            // Convertir le DateTime mutable du planning en Immutable
            $heureDebutImmutable = \DateTimeImmutable::createFromMutable($heureDebut)->setDate(
                $today->format('Y'), $today->format('m'), $today->format('d')
            );
            $heureLimite = $heureDebutImmutable->modify("+$tolerance minutes");

            if($now > $heureLimite){
                $canPointerEntree = false;
                $message = "Heure d'entrée dépassée. Tolérance: $tolerance min";
            }
        }

        return $this->render('employe/pointage/index.html.twig', [
            'pointage' => $pointage,
            'planning' => $planning,
            'canPointerEntree' => $canPointerEntree,
            'canPointerSortie' => $canPointerSortie,
            'message' => $message,
            'heuresTravaillees' => $pointage? $this->calculHeures($pointage) : '0h 00min'
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
}

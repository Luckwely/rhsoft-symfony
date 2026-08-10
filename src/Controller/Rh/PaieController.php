<?php

namespace App\Controller\Rh;

use App\Entity\Employe;
use App\Entity\Paie;
use App\Repository\PaieRepository;
use App\Repository\PointageRepository;
use App\Service\PaieCalculatorService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/rh')]
#[IsGranted('ROLE_RH')]
final class PaieController extends AbstractController
{
    #[Route('/preparer', name: 'app_rh_paie_preparer')]
    public function preparer(PaieRepository $paieRepository): Response
    {
        $paies = $paieRepository->findAll();

        return $this->render('rh/paie/preparer.html.twig', [
            'paies' => $paies,
        ]);
    }

    #[Route('/generer', name: 'app_rh_paie_generer')]
    public function generer(PaieRepository $paieRepository): Response
    {
        $paies = $paieRepository->findAll();

        return $this->render('rh/paie/generer.html.twig', [
            'paies' => $paies,
        ]);
    }

    #[Route('/calculer/{id}', name: 'app_rh_paie_calculer', methods: ['POST'])]
    public function calculer(Employe $employe, PaieCalculatorService $calculator, PointageRepository $pointageRepository, EntityManagerInterface $em): Response
    {
        // 1. Définir la période (par exemple, le mois en cours)
        $mois = (int) date('m');
        $annee = (int) date('Y');

        // 2. Récupérer automatiquement les jours d'absence via le pointage
        // (En supposant que votre PointageRepository a une méthode pour compter les absences du mois)
        $joursAbsents = $pointageRepository->countAbsencesByEmployeAndMonth($employe, $mois, $annee);

        // 3. Préparer les données dynamiques de l'employé
        $dataInput = [
            'salaire_base' => $employe->getSalaireBase(),
            'jours_absents' => $joursAbsents, // Valeur automatique issue du pointage
            'panier_repas' => $employe->getPanierRepas() ?? 0, // Ou récupéré de l'entité/contrat
            'transport' => $employe->getTransport() ?? 0,
            'anciennete' => $employe->getAnciennete() ?? 0,
            'personnes_a_charge' => $employe->getPersonnesACharge() ?? 0,
        ];

        // 4. Calculer la paie
        $resultatPaie = $calculator->calculerPaie($dataInput);

        // 5. Enregistrer le résultat dans l'entité Paie
        $paie = new Paie();
        $paie->setEmployee($employe)
            ->setMois($mois)
            ->setAnnee($annee)
            ->setSalaireBrut($resultatPaie['salaire_brut'])
            ->setCotisations(sprintf('%.2f', $resultatPaie['cnaps'] + $resultatPaie['ostie']))
            ->setSalaireNet($resultatPaie['salaire_net'])
            ->setStatus('calculée');

        $em->persist($paie);
        $em->flush();

        $this->addFlash('success', 'Paie calculée et enregistrée avec succès !');

        return $this->redirectToRoute('app_rh_paie_preparer');
    }
}

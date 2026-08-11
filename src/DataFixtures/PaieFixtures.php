<?php

namespace App\DataFixtures;

use App\Entity\Paie;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PaieFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $statuts = ['payé', 'en attente', 'validé'];

        // On suppose que vos UserFixtures ont généré 9 utilisateurs (index 1 à 9)
        for ($userIndex = 1; $userIndex <= 9; $userIndex++) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);

            // Génération de la paie pour les 2 derniers mois (ex: mois 7 et 8 de l'année 2026)
            $moisActuel = (int) date('m');
            $anneeActuelle = (int) date('Y');

            for ($i = 0; $i < 2; $i++) {
                $paie = new Paie();
                $paie->setEmployee($employee);

                // Calcul du mois/année en reculant de $i mois
                $moisCible = $moisActuel - $i;
                $anneeCible = $anneeActuelle;
                if ($moisCible <= 0) {
                    $moisCible += 12;
                    $anneeCible--;
                }

                $paie->setMois($moisCible);
                $paie->setAnnee($anneeCible);

                // Montants financiers fictifs mais réalistes (stockés en string pour les types DECIMAL)
                $brut = $faker->numberBetween(2000, 5000);
                $cotisations = round($brut * 0.22, 2); // Environ 22% de cotisations
                $net = round($brut - $cotisations, 2);

                $paie->setSalaireBrut((string) $brut);
                $paie->setCotisations((string) $cotisations);
                $paie->setSalaireNet((string) $net);

                $paie->setStatus($faker->randomElement($statuts));

                $manager->persist($paie);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
        ];
    }
}

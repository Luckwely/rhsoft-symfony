<?php

namespace App\DataFixtures;

use App\Entity\Planning;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PlanningFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $daysOfWeek = ['lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi'];
        $typesJour = [Planning::TYPE_TRAVAIL, Planning::TYPE_TRAVAIL, Planning::TYPE_TRAVAIL, Planning::TYPE_REPOS];
        $statuts = [Planning::STATUT_BROUILLON, Planning::STATUT_VALIDE];

        // UserFixtures génère désormais 5 utilisateurs par entreprise (admin, RH, manager,
        // 2 employés) x 3 entreprises = 15 utilisateurs (index 1 à 15).
        for ($userIndex = 1; $userIndex <= 15; $userIndex++) {
            /** @var User $user */
            $user = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $user->getEntreprise();

            // Définition d'un début de semaine fixe (ex: le lundi de la semaine en cours)
            $weekStart = new \DateTimeImmutable('monday this week');

            foreach ($daysOfWeek as $index => $day) {
                $planning = new Planning();

                $planning->setUser($user);
                $planning->setEntreprise($entreprise);
                $planning->setWeekStart($weekStart);
                $planning->setDayOfWeek($day);

                // Type de jour aléatoire (majoritairement du travail)
                $typeJour = $faker->randomElement($typesJour);
                $planning->setTypeJour($typeJour);

                $status = $faker->randomElement($statuts);
                $planning->setStatus($status);

                if ($typeJour === Planning::TYPE_TRAVAIL) {
                    $planning->setHeureDebut(new \DateTimeImmutable('08:00:00'));
                    $planning->setHeureFin(new \DateTimeImmutable('17:00:00'));
                    
                }

                if ($status === Planning::STATUT_VALIDE) {
                    /** @var User $admin */
                    $admin = $this->getReference('user_1', User::class);
                    $planning->setValidatedBy($admin);
                    $planning->setValidatedAt(new \DateTimeImmutable('-1 day'));
                }

                $manager->persist($planning);
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

<?php

namespace App\DataFixtures;

use App\Entity\Pointage;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class PointageFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $statuts = ['present', 'retard', 'absent', 'conge'];

        // On suppose que nous avons 9 utilisateurs créés dans UserFixtures (indexes 1 à 9)
        for ($userIndex = 1; $userIndex <= 9; $userIndex++) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            // Création de 5 pointages (jours différents) pour chaque employé
            for ($i = 0; $i < 5; $i++) {
                $pointage = new Pointage();

                $pointage->setEmployee($employee);
                $pointage->setEntreprise($entreprise);

                // Date du pointage (ex: les 5 derniers jours)
                $dateImmutable = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-10 days', 'now'));
                $pointage->setDate($dateImmutable);

                $statut = $faker->randomElement($statuts);
                $pointage->setStatut($statut);

                if ($statut !== 'absent') {
                    // Heures prévues
                    $pointage->setHeurePrevueDebut(new \DateTimeImmutable('08:00:00'));
                    $pointage->setHeurePrevueFin(new \DateTimeImmutable('17:00:00'));
                    $pointage->setPausePrevueMinutes(60);

                    // Heures réelles d'entrée et sortie
                    $heureEntree = $statut === 'retard' ? '09:15:00' : '08:00:00';
                    $pointage->setHeureEntree(new \DateTimeImmutable($heureEntree));
                    $pointage->setHeureSortie(new \DateTimeImmutable('17:00:00'));

                    $pointage->setPauseMinutes(60);
                    $pointage->setPauseDurationMinutes(60);

                    // Validation aléatoire
                    $valide = $faker->boolean(80);
                    $pointage->setValide($valide);

                    if ($valide && $statut === 'retard') {
                        $pointage->setMotifCorrection('Retard toléré pour transport en commun.');
                        // On peut lier un admin (ex: user_1)
                        $admin = $this->getReference('user_1', User::class);
                        $pointage->setCorrigePar($admin);
                        $pointage->setCorrigeLe(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-5 days', 'now')));
                    }
                }

                $manager->persist($pointage);
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

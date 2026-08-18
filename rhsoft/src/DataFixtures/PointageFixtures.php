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

        // On suppose que vos UserFixtures ont généré 9 utilisateurs (index 1 à 9)
        for ($userIndex = 1; $userIndex <= 9; $userIndex++) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            // Création de 5 pointages sur des jours distincts pour chaque employé
            for ($i = 0; $i < 5; $i++) {
                $pointage = new Pointage();

                $pointage->setEmployee($employee);
                $pointage->setEntreprise($entreprise);

                // Utilisation d'un décalage fixe ($i) pour garantir des dates uniques par utilisateur
                $dateImmutable = new \DateTimeImmutable("-$i days");
                $pointage->setDate($dateImmutable);

                $statut = $faker->randomElement($statuts);
                $pointage->setStatut($statut);

                if ($statut !== 'absent') {
                    // Horaires prévus
                    $pointage->setHeurePrevueDebut(new \DateTimeImmutable('08:00:00'));
                    $pointage->setHeurePrevueFin(new \DateTimeImmutable('17:00:00'));
                    $pointage->setPausePrevueMinutes(60);

                    // Horaires réels d'entrée et sortie
                    $heureEntree = $statut === 'retard' ? '09:15:00' : '08:00:00';
                    $pointage->setHeureEntree(new \DateTimeImmutable($heureEntree));
                    $pointage->setHeureSortie(new \DateTimeImmutable('17:00:00'));

                    $pointage->setPauseMinutes(60);
                    $pointage->setPauseDurationMinutes(60);

                    // Validation aléatoire
                    $valide = $faker->boolean(80);
                    $pointage->setValide($valide);

                    // Si le pointage est validé et en retard, on ajoute un motif et un admin correcteur
                    if ($valide && $statut === 'retard') {
                        $pointage->setMotifCorrection('Retard toléré pour transport en commun.');
                        /** @var User $admin */
                        $admin = $this->getReference('user_1', User::class);
                        $pointage->setCorrigePar($admin);
                        $pointage->setCorrigeLe(new \DateTimeImmutable('-1 day'));
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

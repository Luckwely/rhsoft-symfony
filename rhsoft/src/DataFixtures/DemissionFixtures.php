<?php

namespace App\DataFixtures;

use App\Entity\Demission;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class DemissionFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $statuts = ['en_attente', 'validee', 'refusee'];
        $motifs = [
            'Reconversion professionnelle',
            'Déménagement dans une autre région',
            'Opportunité dans une nouvelle entreprise',
            'Raisons personnelles et familiales'
        ];

        // On crée quelques demandes de démission pour certains utilisateurs (ex: index 3, 6 et 9)
        $employesConcernes = [3, 6, 9];

        foreach ($employesConcernes as $userIndex) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            $demission = new Demission();
            $demission->setEmployee($employee);
            $demission->setEntreprise($entreprise);

            // Date de la demande (dans les 30 derniers jours)
            $dateDemande = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-30 days', '-10 days'));
            $demission->setDateDemande($dateDemande);

            // Date de départ prévue (dans le futur par rapport à la demande)
            $dateDepart = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('now', '+2 months'));
            $demission->setDateDepart($dateDepart);

            $demission->setMotif($faker->randomElement($motifs));
            $demission->setStatut($faker->randomElement($statuts));

            $manager->persist($demission);
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

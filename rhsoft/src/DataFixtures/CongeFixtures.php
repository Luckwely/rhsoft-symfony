<?php

namespace App\DataFixtures;

use App\Entity\Conge;
use App\Entity\TypeConge;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CongeFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $statuts = [Conge::STATUS_DEMANDE, Conge::STATUS_VALIDE, Conge::STATUS_REFUSE];

        // Récupération de tous les types de congé existants en base
        $typeCongeRepository = $manager->getRepository(TypeConge::class);
        $typesConge = $typeCongeRepository->findAll();

        if (empty($typesConge)) {
            return; // Sécurité si aucun TypeConge n'a été créé au préalable
        }

        // On génère des demandes de congés pour les utilisateurs (index 1 à 9)
        for ($userIndex = 1; $userIndex <= 9; $userIndex++) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            // Création de 2 demandes de congé par employé
            for ($i = 0; $i < 2; $i++) {
                $conge = new Conge();

                $conge->setEmployee($employee);
                $conge->setEntreprise($entreprise);

                /** @var TypeConge $typeConge */
                $typeConge = $faker->randomElement($typesConge);
                $conge->setTypeConge($typeConge);

                // Dates de début et de fin (dans le futur proche)
                $dateDebut = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('+5 days', '+2 months'));
                $nbJours = $faker->randomFloat(1, 1, 5); // Entre 1 et 5 jours
                $dateFin = $dateDebut->modify('+' . ((int) $nbJours - 1) . ' days');

                $conge->setDateDebut($dateDebut);
                $conge->setDateFin($dateFin);
                $conge->setNbJours($nbJours);

                $statut = $faker->randomElement($statuts);
                $conge->setStatut($statut);
                $conge->setMotif($faker->sentence());

                // Si le congé est validé, on renseigne l'administrateur valideur
                if ($statut === Conge::STATUS_VALIDE) {
                    /** @var User $admin */
                    $admin = $this->getReference('user_1', User::class);
                    $conge->setValidePar($admin);
                    $conge->setValideLe(new \DateTimeImmutable('-1 day'));
                }

                $manager->persist($conge);
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            TypeCongeFixtures::class, // Assurez-vous que vos types de congés sont chargés avant
        ];
    }
}

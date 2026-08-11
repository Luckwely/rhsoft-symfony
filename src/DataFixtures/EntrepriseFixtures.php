<?php

namespace App\DataFixtures;

use App\Entity\Entreprise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class EntrepriseFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        // Création de 3 entreprises fictives aux profils variés
        $plans = ['standard', 'premium', 'entreprise'];
        $statuses = ['actif', 'suspendu', 'essai'];

        for ($i = 1; $i <= 3; $i++) {
            $entreprise = new Entreprise();
            $nom = $faker->company;

            $entreprise->setNom($nom);
            $entreprise->setNif($faker->numerify('##########'));
            $entreprise->setLogo('default-logo.png');
            $entreprise->setAdresse($faker->address);
            $entreprise->setTel($faker->phoneNumber);
            $entreprise->setEmail($faker->companyEmail);
            $entreprise->setStatus($faker->randomElement($statuses));
            $entreprise->setPlan($faker->randomElement($plans));
            $entreprise->setDateFinAbonnement($faker->dateTimeBetween('now', '+1 year'));
            $entreprise->setStripeCustomerId('cus_' . $faker->regexify('[A-Za-z0-9]{14}'));
            $entreprise->setStripeSubscriptionId('sub_' . $faker->regexify('[A-Za-z0-9]{14}'));
            $entreprise->setSlug(strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $nom))));
            $entreprise->setCreatedAt($faker->dateTimeBetween('-1 year', 'now'));

            // Paramètres spécifiques du multi-tenant / modules RH
            $entreprise->setPrixMois($faker->randomElement([49.99, 99.00, 199.50]));
            $entreprise->setModulePaie($faker->boolean(70));      // 70% de chance d'être à true
            $entreprise->setModulePointage($faker->boolean(85)); // 85% de chance
            $entreprise->setModuleRh($faker->boolean(90));       // 90% de chance
            $entreprise->setToleranceRetard($faker->randomElement([10, 15, 20]));

            $manager->persist($entreprise);

            // Enregistrement d'une référence pour l'associer aux futurs utilisateurs
            $this->addReference('entreprise_' . $i, $entreprise);
        }

        $manager->flush();
    }
}

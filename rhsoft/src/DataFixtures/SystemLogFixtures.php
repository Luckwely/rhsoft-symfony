<?php

namespace App\DataFixtures;

use App\Entity\SystemLog;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class SystemLogFixtures extends Fixture
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $levels = ['INFO', 'WARNING', 'ERROR', 'CRITICAL'];
        $messages = [
            'Tentative de connexion réussie.',
            'Échec de l\'authentification : mot de passe incorrect.',
            'Mise à jour des paramètres de l\'entreprise effectuée.',
            'Génération du rapport de paie mensuel.',
            'Erreur lors de la synchronisation avec le service de paiement.',
            'Création d\'un nouveau compte utilisateur.',
            'Modification du profil employé.',
        ];

        $sourceFiles = ['AuthController.php', 'EmployeeController.php', 'BillingService.php', 'PointageRepository.php'];

        // Création de 15 logs système fictifs
        for ($i = 0; $i < 15; $i++) {
            $log = new SystemLog();

            // Correction ici : conversion en DateTimeImmutable
            $log->setLoggedAt(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-1 month', 'now')));
            $log->setLevel($faker->randomElement($levels));
            $log->setMessage($faker->randomElement($messages));
            $log->setSourceFile($faker->randomElement($sourceFiles));
            $log->setIpAddress($faker->ipv4);
            $log->setUserEmail($faker->safeEmail);
            $log->setCompanyName($faker->company);

            $manager->persist($log);
        }

        $manager->flush();
    }
}

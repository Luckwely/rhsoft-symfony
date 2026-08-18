<?php

namespace App\DataFixtures;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture implements DependentFixtureInterface
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $postes = ['Développeur Web', 'Chef de Projet', 'Comptable', 'Responsable RH', 'Technicien Support'];
        $services = ['Technique', 'Management', 'Finance', 'Ressources Humaines'];
        $banques = ['BNI', 'Société Générale', 'BMOI', 'Access Bank'];

        $userIndex = 1;

        // Boucle sur les 3 entreprises créées dans EntrepriseFixtures
        for ($i = 1; $i <= 3; $i++) {
            /** @var Entreprise $entreprise */
            $entreprise = $this->getReference('entreprise_' . $i, Entreprise::class);

            // Création de 3 utilisateurs par entreprise (ex: 1 admin et 2 employés)
            for ($j = 1; $j <= 3; $j++) {
                $user = new User();
                $user->setEntreprise($entreprise);
                $user->setNom($faker->lastName);
                $user->setPrenom($faker->firstName);
                $user->setEmail($faker->unique()->companyEmail);

                // Attribution des rôles (le premier est admin, les autres sont employés)
                if ($j === 1) {
                    $user->setRoles(['ROLE_ADMIN']);
                } else {
                    $user->setRoles(['ROLE_USER']);
                }

                // Hachage du mot de passe (mot de passe par défaut : "password123")
                $hashedPassword = $this->passwordHasher->hashPassword($user, 'password123');
                $user->setPassword($hashedPassword);

                // Informations personnelles et professionnelles
                $user->setAdresse($faker->address);
                $user->setTelephone($faker->phoneNumber);
                $user->setIsActive(true);
                $user->setIsVerified(true);
                $user->setEmailVerifiedAt($faker->dateTimeBetween('-1 year', 'now'));
                $user->setFirstLogin(false);
                $user->setPoste($faker->randomElement($postes));
                $user->setService($faker->randomElement($services));
                $user->setHeuresContractuelles(8.0);
                $user->setSoldeConge(25.0);
                $user->setDateEmbauche($faker->dateTimeBetween('-2 years', 'now'));

                // Informations bancaires (optionnel)
                $user->setBanqueNom($faker->randomElement($banques));
                $user->setBanqueIban('FR76' . $faker->numerify('####################'));
                $user->setBanqueRib($faker->numerify('###################'));

                $manager->persist($user);

                // Enregistrement d'une référence pour de futures fixtures (ex: Pointages, Congés, Plannings)
                $this->addReference('user_' . $userIndex, $user);
                $userIndex++;
            }
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            EntrepriseFixtures::class,
        ];
    }
}

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

            // Création de 5 utilisateurs par entreprise : 1 admin, 1 RH, 1 manager, 2 employés.
            // Auparavant seuls ROLE_ADMIN et ROLE_USER étaient générés : aucun compte RH ou
            // Manager n'existait dans les fixtures, rendant ces rôles impossibles à tester
            // avec des données fraîches (ex: connexion RH, auto-approbation congé/avance...).
            for ($j = 1; $j <= 5; $j++) {
                $user = new User();
                $user->setEntreprise($entreprise);
                $user->setNom($faker->lastName);
                $user->setPrenom($faker->firstName);
                $user->setEmail($faker->unique()->companyEmail);

                // Attribution des rôles : 1 admin, 1 RH, 1 manager, puis des employés.
                // BUG: le cas par défaut donnait ROLE_USER (rôle Symfony implicite ajouté à
                // tout le monde par User::getRoles()), pas ROLE_EMPLOYE. Or access_control
                // (^/employe) et tous les contrôleurs Employe\* exigent explicitement
                // ROLE_EMPLOYE — jamais attribué par ces fixtures. Un compte "employé" de
                // test pouvait donc se connecter (authentification OK) mais se voyait
                // ensuite refuser l'accès à /employe/profile juste après, ce qui ressemble
                // à un échec de connexion. Le flux d'embauche réel (EmployeeFormType /
                // EmployeeCsvImportService) attribuait déjà correctement ROLE_EMPLOYE ;
                // seules ces fixtures de test avaient la valeur par défaut incorrecte.
                $user->setRoles(match ($j) {
                    1 => ['ROLE_ADMIN'],
                    2 => ['ROLE_RH'],
                    3 => ['ROLE_MANAGER'],
                    default => ['ROLE_EMPLOYE'],
                });

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

                // Salaire de base : nécessaire au calcul du plafond d'avance sur salaire
                // (pourcentage du salaire de base) — sans lui, le plafond est nul et aucune
                // demande d'avance n'est possible pour un utilisateur de fixtures. Repris des
                // salaires de base configurés par rôle sur l'entreprise (Paramètres > Salaires),
                // pour rester cohérent avec ce qui est appliqué lors d'une vraie embauche.
                $user->setSalaireBase($entreprise->getSalaireBaseForRoles($user->getRoles()));

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

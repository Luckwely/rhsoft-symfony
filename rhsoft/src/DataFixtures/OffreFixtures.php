<?php

namespace App\DataFixtures;

use App\Entity\Offre;
use App\Entity\Entreprise;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class OffreFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $statuts = ['ouverte', 'fermée', 'pourvue'];
        $titresPostes = [
            'Développeur Web Fullstack (Symfony/React)',
            'Intégrateur Web Tailwind CSS',
            'Administrateur Système & Réseau Linux',
            'Chef de Projet Digital Junior',
            'Commercial BtoB H/F',
            'Assistant RH et Paie'
        ];

        // On suppose que vos EntrepriseFixtures ont généré des entreprises référencées 'entreprise_0', 'entreprise_1', etc.
        // Ou si vous n'avez pas de références d'entreprises directes, on peut en récupérer une via Doctrine.
        $entrepriseRepository = $manager->getRepository(Entreprise::class);
        $entreprises = $entrepriseRepository->findAll();

        if (empty($entreprises)) {
            return; // Sécurité si aucune entreprise n'existe
        }

        // Création de 15 offres d'emploi réparties aléatoirement
        for ($i = 0; $i < 15; $i++) {
            $offre = new Offre();

            // Association avec une entreprise aléatoire parmi celles existantes
            /** @var Entreprise $entrepriseAleatoire */
            $entrepriseAleatoire = $faker->randomElement($entreprises);
            $offre->setEntreprise($entrepriseAleatoire);

            $offre->setTitre($faker->randomElement($titresPostes));
            $offre->setDescription($faker->paragraphs(3, true));
            $offre->setStatus($faker->randomElement($statuts));

            // Date d'expiration (dans le futur)
            $offre->setDateExpiration(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('now', '+2 months')));

            // createdAt est géré par le constructeur de l'entité, mais on peut aussi l'ajuster si besoin

            $manager->persist($offre);
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

<?php

namespace App\DataFixtures;

use App\Entity\Candidature;
use App\Entity\Offre;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CandidatureFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $statuts = ['en_attente', 'entretenu', 'accepte', 'refuse'];
        $sources = ['site', 'linkedin', 'cooptation', 'indeed'];

        // Récupération de toutes les offres d'emploi existantes en base
        $offreRepository = $manager->getRepository(Offre::class);
        $offres = $offreRepository->findAll();

        if (empty($offres)) {
            return; // Sécurité si aucune offre n'existe en base
        }

        // Création de 20 candidatures factices réparties sur les différentes offres
        for ($i = 0; $i < 20; $i++) {
            $candidature = new Candidature();

            /** @var Offre $offreAleatoire */
            $offreAleatoire = $faker->randomElement($offres);
            $candidature->setOffre($offreAleatoire);

            $candidature->setNom($faker->lastName());
            $candidature->setPrenom($faker->firstName());
            $candidature->setEmail($faker->unique()->safeEmail());
            $candidature->setTelephone($faker->phoneNumber());
            $candidature->setCv('cv_' . $faker->slug() . '.pdf');
            $candidature->setSource($faker->randomElement($sources));
            $candidature->setLettreMotivation('lettre_' . $faker->slug() . '.pdf');
            $candidature->setStatut($faker->randomElement($statuts));

            $manager->persist($candidature);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            OffreFixtures::class,
        ];
    }
}

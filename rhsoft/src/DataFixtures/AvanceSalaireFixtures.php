<?php

namespace App\DataFixtures;

use App\Entity\AvanceSalaire;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class AvanceSalaireFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $statuts = [
            AvanceSalaire::STATUS_DEMANDE,
            AvanceSalaire::STATUS_VALIDE,
            AvanceSalaire::STATUS_REFUSE,
            AvanceSalaire::STATUS_REMBOURSE
        ];

        $motifs = [
            'Frais de réparation de véhicule imprévus',
            'Urgence médicale familiale',
            'Achat de matériel informatique pour télétravail',
            'Dépôt de garantie pour un nouveau logement'
        ];

        // On génère des demandes d'avance sur salaire pour quelques utilisateurs (ex: index 2, 5 et 8)
        $employesConcernes = [2, 5, 8];

        foreach ($employesConcernes as $userIndex) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            $avance = new AvanceSalaire();
            $avance->setEmployee($employee);
            $avance->setEntreprise($entreprise);

            // Montant réaliste d'une avance (ex: entre 300 et 1000)
            $avance->setMontant($faker->numberBetween(300, 1000));
            $avance->setMotif($faker->randomElement($motifs));

            $statut = $faker->randomElement($statuts);
            $avance->setStatut($statut);

            // Date de la demande (récente)
            $dateDemande = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-20 days', '-5 days'));
            $avance->setDateDemande($dateDemande);

            // Si validé ou remboursé, on renseigne l'administrateur valideur et éventuellement la date de remboursement
            if (in_array($statut, [AvanceSalaire::STATUS_VALIDE, AvanceSalaire::STATUS_REMBOURSE])) {
                /** @var User $admin */
                $admin = $this->getReference('user_1', User::class);
                $avance->setValidePar($admin);
                $avance->setCommentaire('Demande acceptée selon les conditions internes.');
            }

            if ($statut === AvanceSalaire::STATUS_REMBOURSE) {
                $avance->setDateRemboursement(\DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-4 days', 'now')));
            }

            $manager->persist($avance);
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

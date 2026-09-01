<?php

namespace App\DataFixtures;

use App\Entity\AvanceSalaire;
use App\Entity\Paie;
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

        $motifs = [
            'Frais de réparation de véhicule imprévus',
            'Urgence médicale familiale',
            'Achat de matériel informatique pour télétravail',
            'Dépôt de garantie pour un nouveau logement'
        ];

        // On génère une demande d'avance sur salaire par statut, pour couvrir tout le cycle
        // de vie (y compris "payee", jusqu'ici jamais généré, et les champs de paiement qui
        // vont avec) plutôt que de tirer un statut au hasard sur un petit échantillon.
        // Sous la disposition UserFixtures à 5 utilisateurs/entreprise (admin, RH, manager,
        // employé, employé), on cible ici des employés (index 4, 5, 9, 10, 14) pour ne pas
        // faire porter une demande d'avance par un compte admin.
        $employesConcernes = [
            4 => AvanceSalaire::STATUS_DEMANDE,
            5 => AvanceSalaire::STATUS_REFUSE,
            9 => AvanceSalaire::STATUS_VALIDE,
            10 => AvanceSalaire::STATUS_REMBOURSE,
            14 => AvanceSalaire::STATUS_PAYEE,
        ];

        foreach ($employesConcernes as $userIndex => $statut) {
            /** @var User $employee */
            $employee = $this->getReference('user_' . $userIndex, User::class);
            $entreprise = $employee->getEntreprise();

            $avance = new AvanceSalaire();
            $avance->setEmployee($employee);
            $avance->setEntreprise($entreprise);

            // Montant réaliste d'une avance (ex: entre 300 et 1000)
            $avance->setMontant($faker->numberBetween(300, 1000));
            $avance->setMotif($faker->randomElement($motifs));
            $avance->setStatut($statut);

            // Date de la demande (récente)
            $dateDemande = \DateTimeImmutable::createFromMutable($faker->dateTimeBetween('-20 days', '-10 days'));
            $avance->setDateDemande($dateDemande);

            // Dès que la demande a été traitée par un RH/Admin (validée, payée ou remboursée),
            // on renseigne le valideur et un commentaire.
            if (in_array($statut, [AvanceSalaire::STATUS_VALIDE, AvanceSalaire::STATUS_PAYEE, AvanceSalaire::STATUS_REMBOURSE], true)) {
                /** @var User $admin */
                $admin = $this->getReference('user_1', User::class);
                $avance->setValidePar($admin);
                $avance->setCommentaire('Demande acceptée selon les conditions internes.');
            }

            // Dès que l'avance a été effectivement versée à l'employé (payee ou rembourse),
            // on renseigne les champs de paiement : mode, référence, date et qui a payé.
            if (in_array($statut, [AvanceSalaire::STATUS_PAYEE, AvanceSalaire::STATUS_REMBOURSE], true)) {
                /** @var User $payeur */
                $payeur = $this->getReference('user_1', User::class);
                $modePaiement = $faker->randomElement(AvanceSalaire::MODES_PAIEMENT);

                $datePaiement = \DateTimeImmutable::createFromMutable(
                    $faker->dateTimeBetween($dateDemande->format('Y-m-d'), '-3 days')
                );

                $avance->setModePaiement($modePaiement);
                $avance->setReferencePaiement(strtoupper($faker->bothify('PAY-########')));
                $avance->setDatePaiement($datePaiement);
                $avance->setPayePar($payeur);
            }

            // Si remboursée, elle a en plus été déduite d'une fiche de paie existante :
            // on la rattache et on répercute le montant sur la paie correspondante.
            if ($statut === AvanceSalaire::STATUS_REMBOURSE) {
                $avance->setDateRemboursement($faker->dateTimeBetween('-2 days', 'now'));

                /** @var Paie|null $paie */
                $paie = $manager->getRepository(Paie::class)->findOneBy(
                    ['employee' => $employee],
                    ['annee' => 'DESC', 'mois' => 'DESC']
                );

                if ($paie !== null) {
                    $avance->setPaie($paie);
                    $paie->setMontantAvanceDeduite($avance->getMontant());
                    $manager->persist($paie);
                }
            }

            $manager->persist($avance);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            UserFixtures::class,
            PaieFixtures::class,
        ];
    }
}

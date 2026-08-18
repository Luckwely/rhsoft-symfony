<?php

namespace App\DataFixtures;

use App\Entity\TypeConge;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TypeCongeFixtures extends Fixture
{
    public const TYPE_CP_REFERENCE = 'type_conge_cp';
    public const TYPE_RTT_REFERENCE = 'type_conge_rtt';
    public const TYPE_SS_REFERENCE = 'type_conge_ss';
    public const TYPE_MALADIE_REFERENCE = 'type_conge_maladie';

    public function load(ObjectManager $manager): void
    {
        $typesConges = [
            [
                'nom' => 'Congés Payés',
                'code' => 'CP',
                'joursAnnuels' => 25,
                'paye' => true,
                'reference' => self::TYPE_CP_REFERENCE,
            ],
            [
                'nom' => 'RTT',
                'code' => 'RTT',
                'joursAnnuels' => 10,
                'paye' => true,
                'reference' => self::TYPE_RTT_REFERENCE,
            ],
            [
                'nom' => 'Congé Sans Solde',
                'code' => 'CSS',
                'joursAnnuels' => null,
                'paye' => false,
                'reference' => self::TYPE_SS_REFERENCE,
            ],
            [
                'nom' => 'Congé Maladie',
                'code' => 'MALADIE',
                'joursAnnuels' => null,
                'paye' => true,
                'reference' => self::TYPE_MALADIE_REFERENCE,
            ],
        ];

        foreach ($typesConges as $data) {
            $typeConge = new TypeConge();
            $typeConge->setNom($data['nom']);
            $typeConge->setCode($data['code']);
            $typeConge->setJoursAnnuels($data['joursAnnuels']);
            $typeConge->setPaye($data['paye']);

            $manager->persist($typeConge);

            // Enregistrement d'une référence pour l'utiliser dans d'autres fixtures (ex: CongeFixtures)
            $this->addReference($data['reference'], $typeConge);
        }

        $manager->flush();
    }
}

<?php
namespace App\Repository;

use App\Entity\AvanceSalaire;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<AvanceSalaire> */
class AvanceSalaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvanceSalaire::class);
    }

    /** @return AvanceSalaire[] */
    public function findEnAttenteByEntreprise(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.employee', 'e')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', AvanceSalaire::STATUS_DEMANDE)
            ->orderBy('a.createdAt', 'DESC')
            ->getQuery()->getResult();
    }

    public function getStatsMois(Entreprise $entreprise): array
    {
        $debutMois = new \DateTimeImmutable('first day of this month');
        $total = $this->createQueryBuilder('a')
            ->select('SUM(a.montant)')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.dateDemande >= :debut')
            ->andWhere('a.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('debut', $debutMois)
            ->setParameter('statut', AvanceSalaire::STATUS_VALIDE)
            ->getQuery()->getSingleScalarResult() ?? 0;

        $nbEnAttente = $this->count(['entreprise' => $entreprise, 'statut' => AvanceSalaire::STATUS_DEMANDE]);
        $moyenne = $nbEnAttente > 0 ? $total / $nbEnAttente : 0;

        return ['total' => $total, 'nbEnAttente' => $nbEnAttente, 'moyenne' => $moyenne];
    }
}

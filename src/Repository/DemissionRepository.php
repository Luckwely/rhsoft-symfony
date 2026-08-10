<?php

namespace App\Repository;

use App\Entity\Demission;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demission>
 */
class DemissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demission::class);
    }

    public function countDeparturesByEntrepriseAndYear(Entreprise $entreprise, int $year): int
    {
        $from = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $to = new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $year));

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.entreprise = :entreprise')
            ->andWhere('d.dateDepart BETWEEN :from AND :to')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyDeparturesByEntreprise(Entreprise $entreprise, int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1))->modify('first day of this month 00:00:00');

        return $this->createQueryBuilder('d')
            ->select('YEAR(d.dateDepart) AS annee', 'MONTH(d.dateDepart) AS mois', 'COUNT(d.id) AS total')
            ->where('d.entreprise = :entreprise')
            ->andWhere('d.dateDepart >= :fromDate')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('fromDate', $fromDate)
            ->groupBy('annee', 'mois')
            ->orderBy('annee', 'ASC')
            ->addOrderBy('mois', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

//    /**
//     * @return Demission[] Returns an array of Demission objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('d.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Demission
//    {
//        return $this->createQueryBuilder('d')
//            ->andWhere('d.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

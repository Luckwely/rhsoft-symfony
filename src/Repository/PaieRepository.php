<?php

namespace App\Repository;

use App\Entity\Entreprise;
use App\Entity\Paie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paie>
 */
class PaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paie::class);
    }

    public function sumSalaireBrutByEntrepriseAndMonth(Entreprise $entreprise, int $mois, int $annee): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.salaireBrut), 0) as total')
            ->join('p.employee', 'u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('p.mois = :mois')
            ->andWhere('p.annee = :annee')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function sumSalaireBrutByMonth(int $mois, int $annee): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.salaireBrut), 0) as total')
            ->andWhere('p.mois = :mois')
            ->andWhere('p.annee = :annee')
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function getMonthlyGrossTotals(int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1));
        $fromYm = ((int) $fromDate->format('Y')) * 100 + (int) $fromDate->format('m');

        return $this->createQueryBuilder('p')
            ->select('p.annee AS annee', 'p.mois AS mois', 'COALESCE(SUM(p.salaireBrut), 0) AS total')
            ->where('p.annee * 100 + p.mois >= :fromYm')
            ->setParameter('fromYm', $fromYm)
            ->groupBy('p.annee', 'p.mois')
            ->orderBy('p.annee', 'ASC')
            ->addOrderBy('p.mois', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyGrossTotalsByEntreprise(Entreprise $entreprise, int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1));
        $fromYm = ((int) $fromDate->format('Y')) * 100 + (int) $fromDate->format('m');

        return $this->createQueryBuilder('p')
            ->select('p.annee AS annee', 'p.mois AS mois', 'COALESCE(SUM(p.salaireBrut), 0) AS total')
            ->join('p.employee', 'u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('p.annee * 100 + p.mois >= :fromYm')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('fromYm', $fromYm)
            ->groupBy('p.annee', 'p.mois')
            ->orderBy('p.annee', 'ASC')
            ->addOrderBy('p.mois', 'ASC')
            ->getQuery()
            ->getResult();
    }

//    /**
//     * @return Paie[] Returns an array of Paie objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paie
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

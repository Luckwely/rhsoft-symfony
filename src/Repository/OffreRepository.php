<?php

namespace App\Repository;

use App\Entity\Offre;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Offre>
 */
class OffreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Offre::class);
    }

    /**
     * @return Offre[] Open job offers belonging to the entreprise identified by this slug.
     */
    public function findByEntrepriseSlug(string $slug): array
    {
        return $this->createQueryBuilder('o')
            ->join('o.entreprise', 'e')
            ->andWhere('e.slug = :slug')->setParameter('slug', $slug)
            ->andWhere('o.status = :status')->setParameter('status', 'ouverte')
            ->orderBy('o.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function createOpenOffersQuery(): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', 'ouverte')
            ->orderBy('o.id', 'DESC');
    }

    public function createOpenOffersByEntrepriseSlugQuery(string $slug): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('o')
            ->join('o.entreprise', 'e')
            ->andWhere('e.slug = :slug')
            ->andWhere('o.status = :status')
            ->setParameter('slug', $slug)
            ->setParameter('status', 'ouverte')
            ->orderBy('o.id', 'DESC');
    }

    //    /**
    //     * @return Offre[] Returns an array of Offre objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('o.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Offre
    //    {
    //        return $this->createQueryBuilder('o')
    //            ->andWhere('o.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

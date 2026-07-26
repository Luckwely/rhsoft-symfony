<?php

namespace App\Repository;

use App\Entity\SystemLog;
use Doctrine\ORM\Query;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SystemLog>
 */
class SystemLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SystemLog::class);
    }

    public function findLogsByTypeQuery(string $type, ?string $status = null, ?string $level = null, ?string $search = null): Query
    {
        $qb = $this->createQueryBuilder('l')
            ->andWhere('l.level ' . ($type === 'connection' ? 'IN (:levels)' : 'NOT IN (:levels)'))
            ->setParameter('levels', $type === 'connection' ? ['success', 'failed'] : ['success', 'failed']);

        if ($type === 'connection' && $status) {
            $qb->andWhere('l.level = :status')->setParameter('status', $status);
        }

        if ($type === 'error') {
            if ($level) {
                $qb->andWhere('l.level = :level')->setParameter('level', $level);
            }
            if ($search) {
                $qb->andWhere('l.message LIKE :search OR l.sourceFile LIKE :search')
                   ->setParameter('search', '%' . $search . '%');
            }
        }

        return $qb->orderBy('l.loggedAt', 'DESC')->getQuery();
    }

    public function findLatestCriticalError(): ?SystemLog
    {
        return $this->createQueryBuilder('l')
            ->andWhere('l.level = :crit')
            ->setParameter('crit', 'critical')
            ->orderBy('l.loggedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countByType(string $type): int
    {
        $levels = $type === 'connection' ? ['success', 'failed'] : ['success', 'failed'];
        $qb = $this->createQueryBuilder('l')->select('COUNT(l.id)');

        if ($type === 'connection') {
            $qb->andWhere('l.level IN (:levels)')->setParameter('levels', $levels);
        } else {
            $qb->andWhere('l.level NOT IN (:levels)')->setParameter('levels', $levels);
        }

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

//    /**
//     * @return SystemLog[] Returns an array of SystemLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('s.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?SystemLog
//    {
//        return $this->createQueryBuilder('s')
//            ->andWhere('s.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

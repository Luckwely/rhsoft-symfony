<?php

namespace App\Repository;

use App\Entity\Paie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paie>
 */
class PayslipRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paie::class);
    }

    /**
     * @return Paie[]
     */
    public function findByUserAndYear(User $user, int $year): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.employee = :user') // Changed from owner to employee
            ->andWhere('p.annee = :year')
            ->setParameter('user', $user)
            ->setParameter('year', $year)
            ->orderBy('p.mois', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return int[]
     */
    public function getAvailableYearsForUser(User $user): array
    {
        $results = $this->createQueryBuilder('p')
            ->select('DISTINCT p.annee')
            ->andWhere('p.employee = :user') // Changed from owner to employee
            ->setParameter('user', $user)
            ->orderBy('p.annee', 'DESC')
            ->getQuery()
            ->getSingleColumnResult();

        if (empty($results)) {
            return [(int) date('Y')];
        }

        return array_map('intval', $results);
    }
}

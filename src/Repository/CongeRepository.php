<?php

namespace App\Repository;

use App\Entity\Conge;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conge>
 */
class CongeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conge::class);
    }

    /**
     * Find all leaves for a given enterprise, optionally filtered by status.
     * Useful for Admin and RH dashboards.
     *
     * @return Conge[]
     */
    public function findByEntrepriseAndStatus(Entreprise $entreprise, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('c.createdAt', 'DESC');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')
               ->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Find all leaves submitted by a specific employee.
     * Useful for Agents viewing their own requests.
     *
     * @return Conge[]
     */
    public function findByEmployee(User $employee): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}

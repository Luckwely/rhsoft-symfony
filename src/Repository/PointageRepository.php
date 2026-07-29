<?php

namespace App\Repository;

use App\Entity\Pointage;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Pointage>
 */
class PointageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Pointage::class);
    }

    public function findByDateWithFilters(\DateTimeImmutable $date, ?string $status, ?string $service, ?string $q, Entreprise $entreprise): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p', 'u')
            ->join('p.employee', 'u') // alias = u
            ->where('p.date = :date')->setParameter('date', $date)
            ->andWhere('p.entreprise = :entreprise')->setParameter('entreprise', $entreprise);

        if ($status) {
            $qb->andWhere('p.statut = :status')->setParameter('status', $status);
        }
        if ($service) {
            $qb->andWhere('u.service = :service')->setParameter('service', $service); // FIX: u pas e
        }
        if ($q) {
            $qb->andWhere('u.nom LIKE :q OR u.prenom LIKE :q OR u.email LIKE :q') // FIX: u pas e
               ->setParameter('q', '%'.$q.'%');
        }

        return $qb; // IMPORTANT: return QueryBuilder pour KnpPaginator
    }

    public function getStatsByDate(\DateTimeImmutable $date, Entreprise $entreprise): array
    {
        $qb = $this->createQueryBuilder('p')
            ->select('p.statut, COUNT(p.id) as total')
            ->where('p.date = :date')->setParameter('date', $date)
            ->andWhere('p.entreprise = :entreprise')->setParameter('entreprise', $entreprise)
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();

        $stats = ['present' => 0, 'retard' => 0, 'absent' => 0, 'conge' => 0];
        foreach ($qb as $row) {
            $stats[$row['statut']] = (int)$row['total'];
        }
        return $stats;
    }

    public function isDateValide(\DateTimeImmutable $date, Entreprise $entreprise): bool
    {
        $count = $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.date = :date')->setParameter('date', $date)
            ->andWhere('p.entreprise = :entreprise')->setParameter('entreprise', $entreprise)
            ->andWhere('p.valide = false')
            ->getQuery()->getSingleScalarResult();
        return $count == 0;
    }

    public function validerJournee(\DateTimeImmutable $date, Entreprise $entreprise): void
    {
        $this->createQueryBuilder('p')
            ->update()
            ->set('p.valide', true)
            ->where('p.date = :date')->setParameter('date', $date)
            ->andWhere('p.entreprise = :entreprise')->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->execute();
    }
}

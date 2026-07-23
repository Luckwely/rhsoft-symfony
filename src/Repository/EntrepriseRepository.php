<?php
namespace App\Repository;

use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Entreprise>
 */
class EntrepriseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Entreprise::class);
    }

    public function findAllWithStats(?string $search = null, ?string $status = null): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('e')
            ->select('e', 'COUNT(u.id) as nbUsers', 'a.nom as adminNom', 'a.prenom as adminPrenom', 'a.photo as adminPhoto')
            ->leftJoin('App\Entity\User', 'u', 'WITH', 'u.entreprise = e.id')
            ->leftJoin('App\Entity\User', 'a', 'WITH', 'a.entreprise = e.id AND a.roles LIKE :roleAdmin')
            ->setParameter('roleAdmin', '%ROLE_ADMIN%')
            ->groupBy('e.id')
            ->orderBy('e.nom', 'ASC');

        if ($search) {
            $qb->andWhere('e.nom LIKE :search OR e.email LIKE :search')
               ->setParameter('search', '%'.$search.'%');
        }
        if ($status) {
            $qb->andWhere('e.status = :status')
               ->setParameter('status', $status);
        }
        return $qb->getQuery();
    }

    public function countAll(): int {
        return $this->count([]);
    }

    public function countByStatus(string $status): int {
        return $this->count(['status' => $status]);
    }

    public function findWithAdmin(int $id): ?Entreprise
    {
        return $this->createQueryBuilder('e')
            ->select('e', 'a') // <- hydrate e + a
            ->leftJoin('App\Entity\User', 'a', 'WITH', 'a.entreprise = e.id AND a.roles LIKE :roleAdmin')
            ->setParameter('roleAdmin', '%ROLE_ADMIN%')
            ->andWhere('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneAdminByEntreprise(int $entrepriseId): ?User
    {
        return $this->getEntityManager()->getRepository(User::class)
            ->createQueryBuilder('u')
            ->where('u.entreprise = :id')
            ->andWhere('u.roles LIKE :role')
            ->setParameter('id', $entrepriseId)
            ->setParameter('role', '%ROLE_ADMIN%')
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countUsersByEntreprise(int $entrepriseId): int
    {
        return $this->getEntityManager()->getRepository(User::class)->count(['entreprise' => $entrepriseId]);
    }

    public function findAllWithAbonnementFilters(?string $search, ?string $plan, ?string $status): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            $qb->andWhere('e.nom LIKE :search OR e.email LIKE :search')
            ->setParameter('search', '%'.$search.'%');
        }
        if ($plan) {
            $qb->andWhere('e.plan = :plan')->setParameter('plan', $plan);
        }
        if ($status) {
            $qb->andWhere('e.status = :status')->setParameter('status', $status);
        }

        $qb->orderBy('e.date_fin_abonnement', 'ASC'); // next billing
        return $qb->getQuery();
    }

    public function getAbonnementStats(): array
    {
        $qb = $this->createQueryBuilder('e');
        return [
            'total' => $this->count([]),
            'actifs' => $this->count(['status' => 'active']),
            'annules' => $this->count(['status' => 'canceled']),
            'ca' => $qb->select('SUM(e.prixMois)')
                    ->where('e.status = :s')->setParameter('s', 'active')
                    ->getQuery()->getSingleScalarResult() ?? 0,
        ];
    }

    public function findAllWithModuleFilters(?string $search, ?string $module, ?string $status): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('e');
        if ($search) $qb->andWhere('e.nom LIKE :search')->setParameter('search', '%'.$search.'%');
        if ($module && $status !== null) {
            $qb->andWhere("e.module$module = :status")->setParameter('status', $status === 'active');
        }
        return $qb->getQuery();
    }

    public function getTauxActivation(): string
    {
        $total = $this->count([]) * 3; // 3 modules * nb entreprises
        $actifs = $this->createQueryBuilder('e')
            ->select('SUM(e.modulePaie + e.modulePointage + e.moduleRh)')
            ->getQuery()->getSingleScalarResult();
        return $total > 0 ? round(($actifs / $total) * 100) . '%' : '0%';
    }
}

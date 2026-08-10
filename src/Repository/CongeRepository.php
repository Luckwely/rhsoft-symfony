<?php
namespace App\Repository;

use App\Entity\Conge;
use App\Entity\Entreprise;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Conge> */
class CongeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conge::class);
    }

    /** @return Conge[] */
    public function findByEntrepriseAndStatus(Entreprise $entreprise, ?string $statut = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('c.createdAt', 'DESC');

        if ($statut) {
            $qb->andWhere('c.statut = :statut')->setParameter('statut', $statut);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Conge[] */
    public function findByEmployee(User $employee): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.employee = :employee')
            ->setParameter('employee', $employee)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** @return Conge[] */
    public function findEnAttenteByEntreprise(Entreprise $entreprise, ?string $type = null, ?string $search = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->join('c.employee', 'e')
            ->join('c.typeConge', 't')
            ->where('c.entreprise = :entreprise')
            ->andWhere('c.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Conge::STATUS_DEMANDE)
            ->orderBy('c.createdAt', 'DESC');

        if ($type) {
            $qb->andWhere('t.code = :type')->setParameter('type', $type);
        }
        if ($search) {
            $qb->andWhere('LOWER(e.nom) LIKE LOWER(:search) OR LOWER(e.prenom) LIKE LOWER(:search)')
               ->setParameter('search', '%'.$search.'%');
        }

        return $qb->getQuery()->getResult();
    }

    public function countPendingByEntreprise(Entreprise $entreprise): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.entreprise = :entreprise')
            ->andWhere('c.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Conge::STATUS_DEMANDE)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

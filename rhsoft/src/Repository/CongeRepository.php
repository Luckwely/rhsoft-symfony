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

    public function findRecentlyValidatedByEntreprise(Entreprise $entreprise, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->join('c.employee', 'u')
            ->addSelect('u')
            ->where('c.entreprise = :entreprise')
            ->andWhere('c.statut = :statut')
            ->andWhere('c.valideLe IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', Conge::STATUS_VALIDE)
            ->orderBy('c.valideLe', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findAbsenceMotifsStatsByEntreprise(Entreprise $entreprise, int $month, int $year): array
    {
        $dateDebut = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $year, $month));
        $dateFin = (clone $dateDebut)->modify('last day of this month 23:59:59');

        return $this->createQueryBuilder('c')
            ->select('c.motif as label', 'SUM(c.nbJours) as days') // Replace nbJours with your actual property name if different
            ->join('c.employee', 'e')
            ->where('c.entreprise = :entreprise')
            ->andWhere('c.dateDebut BETWEEN :dateDebut AND :dateFin')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->groupBy('c.motif')
            ->getQuery()
            ->getResult();
    }
}

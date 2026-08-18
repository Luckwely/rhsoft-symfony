<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    /**
     * Used to upgrade (rehash) the user's password automatically over time.
     */
    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function findByEntrepriseWithStats(int $entrepriseId, ?string $search = null, ?string $status = null): \Doctrine\ORM\Query
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.entreprise = :id')
            ->setParameter('id', $entrepriseId);

        if ($search) {
            $qb->andWhere('u.nom LIKE :search OR u.email LIKE :search OR u.prenom LIKE :search')
            ->setParameter('search', '%'.$search.'%');
        }

        // CORRIGÉ : on utilise u.statut
        if ($status) {
            $qb->andWhere('u.statut = :statut')
            ->setParameter('statut', $status);
        }

        $qb->orderBy('u.createdAt', 'DESC'); // u.createdAt existe dans ton Entity
        return $qb->getQuery();
    }

    public function findDistinctServices(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.service')
            ->distinct()
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.service IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('u.service', 'ASC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countByEntreprise(Entreprise $entreprise): int
    {
        return $this->count(['entreprise' => $entreprise]);
    }

    public function findRecentHiresByEntreprise(Entreprise $entreprise, int $limit = 5): array
    {
        return $this->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('u.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByServiceAndEntreprise(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('u')
            ->select('u.service AS service', 'COUNT(u.id) AS total')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.service IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->groupBy('u.service')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    public function countContractsExpiringSoon(Entreprise $entreprise, int $days = 30): int
    {
        $today = new \DateTimeImmutable('today');
        $future = (clone $today)->modify("+{$days} days");

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateSortie IS NOT NULL')
            ->andWhere('u.dateSortie BETWEEN :today AND :future')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('today', $today)
            ->setParameter('future', $future)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countProbationEnding(Entreprise $entreprise, int $probationDays = 90, int $windowDays = 30): int
    {
        $today = new \DateTimeImmutable('today');
        $start = (clone $today)->modify(sprintf('-%d days', $probationDays));
        $end = (clone $today)->modify(sprintf('-%d days', $probationDays - $windowDays));

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateEmbauche IS NOT NULL')
            ->andWhere('u.dateEmbauche BETWEEN :start AND :end')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countEmployeesForAnnualReview(Entreprise $entreprise, int $years = 1): int
    {
        $limitDate = (new \DateTimeImmutable('today'))->modify(sprintf('-%d years', $years));

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateEmbauche IS NOT NULL')
            ->andWhere('u.dateSortie IS NULL')
            ->andWhere('u.dateEmbauche <= :limitDate')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('limitDate', $limitDate)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countHiresByEntrepriseAndYear(Entreprise $entreprise, int $year): int
    {
        $from = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $to = new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $year));

        return (int) $this->createQueryBuilder('u')
            ->select('COUNT(u.id)')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.dateEmbauche BETWEEN :from AND :to')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    //    /**
    //     * @return User[] Returns an array of User objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('u.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?User
    //    {
    //        return $this->createQueryBuilder('u')
    //            ->andWhere('u.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}

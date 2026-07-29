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

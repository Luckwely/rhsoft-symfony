<?php

namespace App\Repository;

use App\Entity\Notification;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/** @extends ServiceEntityRepository<Notification> */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnreadByUser(User $user): int
    {
        return $this->count(['destinataire' => $user, 'lue' => false]);
    }

    /** @return Notification[] */
    public function findRecentByUser(User $user, int $limit = 8): array
    {
        return $this->createQueryBuilder('n')
            ->where('n.destinataire = :user')
            ->setParameter('user', $user)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function markAllAsReadForUser(User $user): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.lue', ':lue')
            ->where('n.destinataire = :user')
            ->andWhere('n.lue = :nonLue')
            ->setParameter('lue', true)
            ->setParameter('nonLue', false)
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }
}

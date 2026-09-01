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

    /**
     * Ancienneté moyenne (en années) des employés actifs ayant une date d'embauche renseignée.
     * Retourne null s'il n'y a pas de date d'embauche exploitable (pas de division par zéro / pas de "fausse" moyenne).
     */
    public function getAverageSeniorityYears(Entreprise $entreprise): ?float
    {
        $dates = $this->createQueryBuilder('u')
            ->select('u.dateEmbauche')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->andWhere('u.dateEmbauche IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true)
            ->getQuery()
            ->getArrayResult();

        if (count($dates) === 0) {
            return null;
        }

        $today = new \DateTimeImmutable('today');
        $totalYears = 0.0;
        foreach ($dates as $row) {
            $dateEmbauche = $row['dateEmbauche'];
            if (!$dateEmbauche instanceof \DateTimeInterface) {
                continue;
            }
            $totalYears += $today->diff($dateEmbauche)->days / 365.25;
        }

        return round($totalYears / count($dates), 1);
    }

    /**
     * Répartition H/F des employés actifs d'une entreprise.
     * Retourne toujours les clés 'H', 'F' et 'non_renseigne' (0 si aucun employé dans la catégorie).
     *
     * @return array{H: int, F: int, non_renseigne: int}
     */
    public function countByGenreAndEntreprise(Entreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.genre AS genre', 'COUNT(u.id) AS total')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true)
            ->groupBy('u.genre')
            ->getQuery()
            ->getArrayResult();

        $distribution = ['H' => 0, 'F' => 0, 'non_renseigne' => 0];
        foreach ($rows as $row) {
            $key = in_array($row['genre'], ['H', 'F'], true) ? $row['genre'] : 'non_renseigne';
            $distribution[$key] += (int) $row['total'];
        }

        return $distribution;
    }

    /**
     * Répartition par tranche d'âge des employés actifs ayant une date de naissance renseignée.
     * Les employés sans date de naissance ne sont pas comptés ici (voir 'withoutBirthdate').
     *
     * @return array{buckets: array<string, int>, withoutBirthdate: int}
     */
    public function getAgeDistributionByEntreprise(Entreprise $entreprise): array
    {
        $rows = $this->createQueryBuilder('u')
            ->select('u.dateNaissance')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.is_active = :active')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('active', true)
            ->getQuery()
            ->getArrayResult();

        $buckets = ['<25' => 0, '25-34' => 0, '35-44' => 0, '45-54' => 0, '55+' => 0];
        $withoutBirthdate = 0;
        $today = new \DateTimeImmutable('today');

        foreach ($rows as $row) {
            $dateNaissance = $row['dateNaissance'];
            if (!$dateNaissance instanceof \DateTimeInterface) {
                $withoutBirthdate++;
                continue;
            }

            $age = $today->diff($dateNaissance)->y;
            $bucket = match (true) {
                $age < 25 => '<25',
                $age < 35 => '25-34',
                $age < 45 => '35-44',
                $age < 55 => '45-54',
                default => '55+',
            };
            $buckets[$bucket]++;
        }

        return ['buckets' => $buckets, 'withoutBirthdate' => $withoutBirthdate];
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

    /**
     * Utilisateurs RH d'une entreprise donnée, seuls habilités à approuver/rejeter/payer une
     * demande d'avance (les routes de la file d'attente sont sous /rh, réservées à ROLE_RH ;
     * un Admin n'a pas d'écran équivalent). Exclut éventuellement le demandeur lui-même
     * (un RH ne peut pas approuver sa propre demande, mais doit tout de même être notifié
     * comme n'importe quel autre demandeur de la confirmation de sa soumission).
     *
     * @return User[]
     */
    public function findRhByEntreprise(Entreprise $entreprise, ?User $exclure = null): array
    {
        $qb = $this->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.roles LIKE :roleRh')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('roleRh', '%ROLE_RH%');

        if ($exclure !== null) {
            $qb->andWhere('u.id != :exclure')
                ->setParameter('exclure', $exclure->getId());
        }

        return $qb->getQuery()->getResult();
    }
}

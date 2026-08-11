<?php
namespace App\Repository;

use App\Entity\Demission;
use App\Entity\Entreprise;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Demission>
 */
class DemissionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Demission::class);
    }

    public function countDeparturesByEntrepriseAndYear(Entreprise $entreprise, int $year): int
    {
        $from = new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', $year));
        $to = new \DateTimeImmutable(sprintf('%d-12-31 23:59:59', $year));

        return (int) $this->createQueryBuilder('d')
            ->select('COUNT(d.id)')
            ->where('d.entreprise = :entreprise')
            ->andWhere('d.dateDepart BETWEEN :from AND :to')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getMonthlyDeparturesByEntreprise(
        Entreprise $entreprise,
        int $months = 6
    ): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1))->modify('first day of this month 00:00:00');

        // 1. On récupère les démissions à partir de la date calculée
        $demissions = $this->createQueryBuilder('d')
            ->where('d.entreprise = :entreprise')
            ->andWhere('d.dateDepart >= :fromDate')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('fromDate', $fromDate)
            ->orderBy('d.dateDepart', 'ASC')
            ->getQuery()
            ->getResult();

        // 2. On groupe et compte par année/mois en PHP pour éviter les fonctions SQL non supportées par défaut
        $groupedData = [];
        foreach ($demissions as $demission) {
            if ($demission->getDateDepart()) {
                $annee = (int) $demission->getDateDepart()->format('Y');
                $mois = (int) $demission->getDateDepart()->format('n');

                $key = $annee . '-' . $mois;

                if (!isset($groupedData[$key])) {
                    $groupedData[$key] = [
                        'annee' => $annee,
                        'mois' => $mois,
                        'total' => 0
                    ];
                }
                $groupedData[$key]['total']++;
            }
        }

        return array_values($groupedData);
    }
}

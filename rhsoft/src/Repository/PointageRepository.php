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
        $results = $this->createQueryBuilder('p')
            ->select('p.statut, COUNT(p.id) as total')
            ->where('p.date = :date')->setParameter('date', $date)
            ->andWhere('p.entreprise = :entreprise')->setParameter('entreprise', $entreprise)
            ->groupBy('p.statut')
            ->getQuery()
            ->getResult();

        $stats = ['present' => 0, 'retard' => 0, 'absent' => 0, 'conge' => 0];
        foreach ($results as $row) {
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

    public function countAbsencesByEmployeAndMonth($employee, int $mois, int $annee): int
    {
        // Créer les dates de début et de fin du mois
        $dateDebut = new \DateTimeImmutable("$annee-$mois-01 00:00:00");
        $dateFin = $dateDebut->modify('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.employee = :employee')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.date BETWEEN :dateDebut AND :dateFin')
            ->setParameter('employee', $employee)
            ->setParameter('statut', 'absent') // Assurez-vous que la valeur correspond à votre base (ex: 'absent')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAbsencesByEntrepriseAndMonth(Entreprise $entreprise, int $mois, int $annee): int
    {
        $dateDebut = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $annee, $mois));
        $dateFin = $dateDebut->modify('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.entreprise = :entreprise')
            ->andWhere('p.statut = :statut')
            ->andWhere('p.date BETWEEN :dateDebut AND :dateFin')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', 'absent')
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestByEntreprise(Entreprise $entreprise, int $limit = 8): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'u')
            ->addSelect('u')
            ->where('p.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('p.date', 'DESC')
            ->addOrderBy('p.heureEntree', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTopOvertimeByEntreprise(Entreprise $entreprise, int $limit = 5): array
    {
        // getHeuresSup() is a computed value (not a persisted column on Pointage),
        // so it cannot be aggregated directly in DQL with SUM(). We fetch the raw
        // pointages for the entreprise and compute the totals in PHP instead.
        $pointages = $this->createQueryBuilder('pt')
            ->join('pt.employee', 'e')
            ->addSelect('e')
            ->where('e.entreprise = :entreprise')
            ->andWhere('pt.heureEntree IS NOT NULL')
            ->andWhere('pt.heureSortie IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getResult();

        $totals = []; // employeeId => ['employee' => User, 'hours' => float]

        foreach ($pointages as $pointage) {
            $employee = $pointage->getEmployee();
            $heuresSup = $pointage->getHeuresSup();

            if (!$employee || $heuresSup <= 0) {
                continue;
            }

            $id = $employee->getId();
            if (!isset($totals[$id])) {
                $totals[$id] = ['employee' => $employee, 'hours' => 0.0];
            }
            $totals[$id]['hours'] += $heuresSup;
        }

        // Estimation d'un coût horaire à partir du salaire de base mensuel
        // (aucun taux horaire n'est stocké séparément dans le modèle actuel).
        foreach ($totals as $id => $data) {
            $employee = $data['employee'];
            $heuresContractJour = $employee->getHeuresContractuelles() ?? 8.0;
            $salaireBase = $employee->getSalaireBase();

            // ~21.66 = 5 jours/semaine x 4.33 semaines/mois : heures contractuelles mensuelles approximatives
            $heuresContractMois = $heuresContractJour * 21.66;
            $tauxHoraire = ($salaireBase && $heuresContractMois > 0) ? $salaireBase / $heuresContractMois : 0.0;

            $totals[$id]['hours'] = round($data['hours'], 2);
            $totals[$id]['cost'] = round($data['hours'] * $tauxHoraire * 1.25, 2); // majoration heures sup de 25%
        }

        usort($totals, static fn(array $a, array $b): int => $b['hours'] <=> $a['hours']);

        return array_slice(array_values($totals), 0, $limit);
    }
}

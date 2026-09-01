<?php

namespace App\Repository;

use App\Entity\Pointage;
use App\Entity\Entreprise;
use App\Service\PaieCalculatorService;
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
        $pointages = $this->createQueryBuilder('p')
            ->where('p.date = :date')
            ->andWhere('p.entreprise = :entreprise')
            ->setParameter('date', $date)
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getResult();

        $stats = ['present' => 0, 'retard' => 0, 'absent' => 0, 'conge' => 0];
        foreach ($pointages as $pointage) {
            $statut = $pointage->getStatutEffectif();
            if (array_key_exists($statut, $stats)) {
                $stats[$statut]++;
            }
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

    /**
     * Nombre de jours réellement présents (pointés "présent" ou "retard", donc hors
     * absences/congés) sur un mois donné pour un employé. Sert de base au calcul du
     * panier repas et de la prime de transport, qui suivent la présence réelle plutôt
     * qu'un montant fixe mensuel : un employé absent ou présent moins de 5 jours dans
     * la semaine touche donc mécaniquement moins que quelqu'un présent tous les jours.
     */
    public function countPresentDaysByEmployeAndMonth($employee, int $mois, int $annee): int
    {
        $dateDebut = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $annee, $mois));
        $dateFin = $dateDebut->modify('last day of this month 23:59:59');

        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.employee = :employee')
            ->andWhere('p.statut IN (:statuts)')
            ->andWhere('p.date BETWEEN :dateDebut AND :dateFin')
            ->setParameter('employee', $employee)
            ->setParameter('statuts', ['present', 'retard'])
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

    /**
     * Taux d'absentéisme (%) sur une année : pointages "absent" / total des pointages enregistrés.
     * Retourne 0.0 si aucun pointage n'a été enregistré sur la période (pas de division par zéro).
     */
    public function getAbsenteeismRateByEntrepriseAndYear(Entreprise $entreprise, int $year): float
    {
        $dateDebut = new \DateTimeImmutable(sprintf('%04d-01-01 00:00:00', $year));
        $dateFin = new \DateTimeImmutable(sprintf('%04d-12-31 23:59:59', $year));

        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.entreprise = :entreprise')
            ->andWhere('p.date BETWEEN :dateDebut AND :dateFin')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getSingleScalarResult();

        if ($total === 0) {
            return 0.0;
        }

        $absent = (int) $this->createQueryBuilder('p')
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

        return round(($absent / $total) * 100, 1);
    }

    /**
     * Retourne les pointages récents d’une entreprise, bornés par une date et limités en volume.
     * Le filtrage est effectué en base afin d’éviter de charger tout l’historique.
     */
    public function findByEntrepriseSince(Entreprise $entreprise, \DateTimeImmutable $since, int $limit = 500): array
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'u')
            ->addSelect('u')
            ->where('p.entreprise = :entreprise')
            ->andWhere('p.date >= :since')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('since', $since)
            ->orderBy('p.date', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
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

    /**
     * Total des heures supplémentaires réellement pointées par un employé sur un mois
     * donné (somme de Pointage::getHeuresSup() sur les pointages du mois). Utilisé par
     * le calcul de paie pour transformer les heures sup en montant réellement payé,
     * au lieu de rester une simple donnée d'affichage du reporting RH.
     */
    public function sumOvertimeHoursByEmployeAndMonth($employee, int $mois, int $annee): float
    {
        $dateDebut = new \DateTimeImmutable(sprintf('%04d-%02d-01 00:00:00', $annee, $mois));
        $dateFin = $dateDebut->modify('last day of this month 23:59:59');

        $pointages = $this->createQueryBuilder('p')
            ->where('p.employee = :employee')
            ->andWhere('p.heureEntree IS NOT NULL')
            ->andWhere('p.heureSortie IS NOT NULL')
            ->andWhere('p.date BETWEEN :dateDebut AND :dateFin')
            ->setParameter('employee', $employee)
            ->setParameter('dateDebut', $dateDebut)
            ->setParameter('dateFin', $dateFin)
            ->getQuery()
            ->getResult();

        $total = 0.0;
        foreach ($pointages as $pointage) {
            $total += $pointage->getHeuresSup();
        }

        return round($total, 2);
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

        // Estimation d'un coût horaire à partir du salaire de base mensuel (aucun taux
        // horaire n'est stocké séparément dans le modèle actuel), avec les mêmes seuils
        // et taux de majoration (30%/50%) que ceux réellement appliqués par
        // PaieCalculatorService lors du calcul de paie — pour que ce que le reporting
        // RH montre comme "coût estimé" corresponde à ce qui sera effectivement payé.
        foreach ($totals as $id => $data) {
            $employee = $data['employee'];
            $heuresContractJour = $employee->getHeuresContractuelles() ?? 8.0;
            $salaireBase = $employee->getSalaireBase();

            $heuresContractMois = $heuresContractJour * PaieCalculatorService::JOURS_OUVRES_PAR_MOIS;
            $tauxHoraire = ($salaireBase && $heuresContractMois > 0) ? $salaireBase / $heuresContractMois : 0.0;

            $heuresSup = $data['hours'];
            $hs30 = min($heuresSup, PaieCalculatorService::SEUIL_HS_30_PAR_MOIS);
            $hs50 = max(0, $heuresSup - PaieCalculatorService::SEUIL_HS_30_PAR_MOIS);

            $totals[$id]['hours'] = round($heuresSup, 2);
            $totals[$id]['cost'] = round(
                ($hs30 * $tauxHoraire * PaieCalculatorService::TAUX_MAJORATION_HS_30)
                    + ($hs50 * $tauxHoraire * PaieCalculatorService::TAUX_MAJORATION_HS_50),
                2
            );
        }

        usort($totals, static fn(array $a, array $b): int => $b['hours'] <=> $a['hours']);

        return array_slice(array_values($totals), 0, $limit);
    }
}

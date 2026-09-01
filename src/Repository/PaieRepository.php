<?php

namespace App\Repository;

use App\Entity\Entreprise;
use App\Entity\Paie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Paie>
 */
class PaieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Paie::class);
    }

    public function createByEntrepriseAndPeriodQueryBuilder(Entreprise $entreprise, int $mois, int $annee): \Doctrine\ORM\QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->join('p.employee', 'e')
            ->addSelect('e')
            ->where('e.entreprise = :entreprise')
            ->andWhere('p.mois = :mois')
            ->andWhere('p.annee = :annee')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->orderBy('e.nom', 'ASC')
            ->addOrderBy('e.prenom', 'ASC');
    }

    /** @return Paie[] */
    public function findByEntrepriseAndPeriod(Entreprise $entreprise, int $mois, int $annee): array
    {
        return $this->createByEntrepriseAndPeriodQueryBuilder($entreprise, $mois, $annee)
            ->getQuery()->getResult();
    }

    public function findOneByEmployeeAndPeriod(User $employee, int $mois, int $annee): ?Paie
    {
        return $this->createQueryBuilder('p')
            ->where('p.employee = :employee')
            ->andWhere('p.mois = :mois')
            ->andWhere('p.annee = :annee')
            ->setParameter('employee', $employee)
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Répartition par service pour une entreprise et une période :
     * nombre d'employés payables, nombre déjà calculés/payés et masse salariale.
     *
     * @return array<string, array{service: string, total_employees: int, calculated: int, paid: int, masse: float}>
     */
    public function getServiceBreakdownByEntrepriseAndPeriod(Entreprise $entreprise, int $mois, int $annee): array
    {
        $employees = $this->getEntityManager()->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.salaireBase IS NOT NULL')
            ->setParameter('entreprise', $entreprise)
            ->getQuery()
            ->getResult();

        $paies = $this->findByEntrepriseAndPeriod($entreprise, $mois, $annee);
        $paieByEmployeeId = [];
        foreach ($paies as $paie) {
            $paieByEmployeeId[$paie->getEmployee()->getId()] = $paie;
        }

        $breakdown = [];
        foreach ($employees as $employee) {
            $service = $employee->getService() ?: 'Non affecté';
            if (!isset($breakdown[$service])) {
                $breakdown[$service] = [
                    'service' => $service,
                    'total_employees' => 0,
                    'calculated' => 0,
                    'paid' => 0,
                    'masse' => 0.0,
                ];
            }

            $breakdown[$service]['total_employees']++;

            $paie = $paieByEmployeeId[$employee->getId()] ?? null;
            if ($paie) {
                $breakdown[$service]['calculated']++;
                $breakdown[$service]['masse'] += (float) $paie->getSalaireBrut();
                if ($paie->isPaid()) {
                    $breakdown[$service]['paid']++;
                }
            } else {
                $breakdown[$service]['masse'] += (float) $employee->getSalaireBase();
            }
        }

        ksort($breakdown);

        return $breakdown;
    }

    /**
     * Liste des employés payables (salaire de base défini) d'une entreprise,
     * sans fiche de paie calculée pour la période donnée.
     *
     * @return User[]
     */
    public function findEmployeesWithoutPaieForPeriod(Entreprise $entreprise, int $mois, int $annee): array
    {
        $paidEmployeeIds = array_map(
            static fn (Paie $p) => $p->getEmployee()->getId(),
            $this->findByEntrepriseAndPeriod($entreprise, $mois, $annee)
        );

        $qb = $this->getEntityManager()->getRepository(User::class)->createQueryBuilder('u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('u.salaireBase IS NOT NULL')
            ->setParameter('entreprise', $entreprise);

        if (!empty($paidEmployeeIds)) {
            $qb->andWhere('u.id NOT IN (:ids)')->setParameter('ids', $paidEmployeeIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Périodes (mois/année) distinctes disponibles pour une entreprise, les plus récentes en premier.
     *
     * @return array<int, array{mois: int, annee: int}>
     */
    public function getAvailablePeriods(Entreprise $entreprise): array
    {
        return $this->createQueryBuilder('p')
            ->select('DISTINCT p.mois AS mois', 'p.annee AS annee')
            ->join('p.employee', 'e')
            ->where('e.entreprise = :entreprise')
            ->setParameter('entreprise', $entreprise)
            ->orderBy('p.annee', 'DESC')
            ->addOrderBy('p.mois', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function sumSalaireBrutByMonth(int $mois, int $annee): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('COALESCE(SUM(p.salaireBrut), 0) as total')
            ->andWhere('p.mois = :mois')
            ->andWhere('p.annee = :annee')
            ->setParameter('mois', $mois)
            ->setParameter('annee', $annee)
            ->getQuery()
            ->getSingleScalarResult();

        return (float) $result;
    }

    public function getMonthlyGrossTotals(int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1));
        $fromYm = ((int) $fromDate->format('Y')) * 100 + (int) $fromDate->format('m');

        return $this->createQueryBuilder('p')
            ->select('p.annee AS annee', 'p.mois AS mois', 'COALESCE(SUM(p.salaireBrut), 0) AS total')
            ->where('p.annee * 100 + p.mois >= :fromYm')
            ->setParameter('fromYm', $fromYm)
            ->groupBy('p.annee', 'p.mois')
            ->orderBy('p.annee', 'ASC')
            ->addOrderBy('p.mois', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getMonthlyGrossTotalsByEntreprise(Entreprise $entreprise, int $months): array
    {
        $today = new \DateTimeImmutable('today');
        $fromDate = (clone $today)->modify(sprintf('-%d months', $months - 1));
        $fromYm = ((int) $fromDate->format('Y')) * 100 + (int) $fromDate->format('m');

        return $this->createQueryBuilder('p')
            ->select('p.annee AS annee', 'p.mois AS mois', 'COALESCE(SUM(p.salaireBrut), 0) AS total')
            ->join('p.employee', 'u')
            ->where('u.entreprise = :entreprise')
            ->andWhere('p.annee * 100 + p.mois >= :fromYm')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('fromYm', $fromYm)
            ->groupBy('p.annee', 'p.mois')
            ->orderBy('p.annee', 'ASC')
            ->addOrderBy('p.mois', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function getServiceCostsByEntrepriseAndMonth(Entreprise $entreprise, int $month, int $year): array
    {
        return $this->createQueryBuilder('p')
            ->select('e.service as service', 'COUNT(e.id) as employeeCount', 'SUM(p.salaireBrut) as payrollMass')
            ->join('p.employee', 'e') // Changed from p.employe to p.employee
            ->where('e.entreprise = :entreprise')
            ->andWhere('p.mois = :month AND p.annee = :year')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->groupBy('e.service')
            ->getQuery()
            ->getResult();
    }

    public function sumSalaireBrutByEntrepriseAndMonth(Entreprise $entreprise, int $month, int $year): float
    {
        return (float) $this->createQueryBuilder('p')
            ->select('SUM(p.salaireBrut)')
            ->join('p.employee', 'e') // Changed from p.employe to p.employee
            ->where('e.entreprise = :entreprise')
            ->andWhere('p.mois = :month AND p.annee = :year')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('month', $month)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Masse salariale brute totale d'une entreprise sur une année (toutes les fiches de paie, tous mois confondus).
     */
    public function sumSalaireBrutByEntrepriseAndYear(Entreprise $entreprise, int $year): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.salaireBrut)')
            ->join('p.employee', 'e')
            ->where('e.entreprise = :entreprise')
            ->andWhere('p.annee = :year')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('year', $year)
            ->getQuery()
            ->getSingleScalarResult();

        return $result !== null ? (float) $result : 0.0;
    }

//    /**
//     * @return Paie[] Returns an array of Paie objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('p.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?Paie
//    {
//        return $this->createQueryBuilder('p')
//            ->andWhere('p.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}

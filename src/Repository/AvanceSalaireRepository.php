<?php
namespace App\Repository;

use App\Entity\AvanceSalaire;
use App\Entity\Entreprise;
use App\Entity\Paie;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/** @extends ServiceEntityRepository<AvanceSalaire> */
class AvanceSalaireRepository extends ServiceEntityRepository
{
    /**
     * Statuts qui représentent de l'argent encore "en cours" (déjà demandé et/ou versé,
     * pas encore remboursé), à comptabiliser dans l'encours d'un employé pour le plafond.
     */
    private const STATUTS_EN_COURS = [
        AvanceSalaire::STATUS_DEMANDE,
        AvanceSalaire::STATUS_VALIDE,
        AvanceSalaire::STATUS_PAYEE,
    ];

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AvanceSalaire::class);
    }

    public function createEnAttenteByEntrepriseQueryBuilder(Entreprise $entreprise, ?User $exclureEmployee = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.employee', 'e')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', AvanceSalaire::STATUS_DEMANDE)
            ->orderBy('a.createdAt', 'DESC');

        if ($exclureEmployee) {
            // Un RH ne doit pas voir sa propre demande d'avance dans sa propre file de
            // validation : elle relève du niveau au-dessus (Admin), pas de lui-même
            // (RH étant le seul rôle habilité à approuver les avances au quotidien).
            $qb->andWhere('a.employee != :exclureEmployee')->setParameter('exclureEmployee', $exclureEmployee);
        }

        return $qb;
    }

    /** @return AvanceSalaire[] */
    public function findEnAttenteByEntreprise(Entreprise $entreprise, ?User $exclureEmployee = null): array
    {
        return $this->createEnAttenteByEntrepriseQueryBuilder($entreprise, $exclureEmployee)
            ->getQuery()->getResult();
    }

    /**
     * Avances approuvées (validées par RH/Admin) mais pas encore effectivement versées à
     * l'employé : c'est la file d'attente de paiement à traiter par RH.
     */
    public function createAVerserByEntrepriseQueryBuilder(Entreprise $entreprise, ?User $exclureEmployee = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('a')
            ->join('a.employee', 'e')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('statut', AvanceSalaire::STATUS_VALIDE)
            ->orderBy('a.createdAt', 'ASC');

        if ($exclureEmployee) {
            // Même logique que pour l'approbation : un RH ne doit pas pouvoir se marquer
            // lui-même sa propre avance comme "versée", c'est aussi un acte de décision.
            $qb->andWhere('a.employee != :exclureEmployee')->setParameter('exclureEmployee', $exclureEmployee);
        }

        return $qb;
    }

    /** @return AvanceSalaire[] */
    public function findAVerserByEntreprise(Entreprise $entreprise, ?User $exclureEmployee = null): array
    {
        return $this->createAVerserByEntrepriseQueryBuilder($entreprise, $exclureEmployee)
            ->getQuery()->getResult();
    }

    public function getStatsMois(Entreprise $entreprise): array
    {
        $debutMois = new \DateTimeImmutable('first day of this month');
        $total = $this->createQueryBuilder('a')
            ->select('SUM(a.montant)')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.dateDemande >= :debut')
            ->andWhere('a.statut = :statut')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('debut', $debutMois)
            ->setParameter('statut', AvanceSalaire::STATUS_VALIDE)
            ->getQuery()->getSingleScalarResult() ?? 0;

        $nbEnAttente = $this->count(['entreprise' => $entreprise, 'statut' => AvanceSalaire::STATUS_DEMANDE]);
        $moyenne = $nbEnAttente > 0 ? $total / $nbEnAttente : 0;

        $nbAVerser = $this->count(['entreprise' => $entreprise, 'statut' => AvanceSalaire::STATUS_VALIDE]);

        return ['total' => $total, 'nbEnAttente' => $nbEnAttente, 'moyenne' => $moyenne, 'nbAVerser' => $nbAVerser];
    }

    /**
     * Somme des avances "en cours" (demandées, approuvées ou versées mais pas encore
     * remboursées) pour un employé. Sert de base au calcul du plafond disponible.
     */
    public function sumEncoursByEmployee(User $employee): float
    {
        $total = $this->createQueryBuilder('a')
            ->select('SUM(a.montant)')
            ->where('a.employee = :employee')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('employee', $employee)
            ->setParameter('statuts', self::STATUTS_EN_COURS)
            ->getQuery()->getSingleScalarResult();

        return $total !== null ? (float) $total : 0.0;
    }

    /**
     * Vrai si l'employé a déjà une demande d'avance en attente de décision RH.
     * Empêche l'accumulation de plusieurs demandes simultanées non traitées.
     */
    public function hasDemandeEnAttente(User $employee): bool
    {
        return $this->count(['employee' => $employee, 'statut' => AvanceSalaire::STATUS_DEMANDE]) > 0;
    }

    /**
     * Avances approuvées ou déjà versées à un employé (statuts valide/payee) et pas encore
     * rattachées à une fiche de paie : ce sont les montants à déduire du salaire net lors
     * du calcul de la prochaine paie.
     *
     * @return AvanceSalaire[]
     */
    public function findDeductiblesByEmployee(User $employee, ?Paie $paieEnCours = null): array
    {
        $qb = $this->createQueryBuilder('a')
            ->where('a.employee = :employee')
            ->andWhere('a.statut IN (:statuts)')
            ->setParameter('employee', $employee)
            ->setParameter('statuts', [AvanceSalaire::STATUS_VALIDE, AvanceSalaire::STATUS_PAYEE]);

        if ($paieEnCours !== null) {
            $qb->andWhere('a.paie IS NULL OR a.paie = :paie')
                ->setParameter('paie', $paieEnCours);
        } else {
            $qb->andWhere('a.paie IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Avances rattachées à une fiche de paie donnée (utilisé au moment du paiement effectif
     * de la paie pour les faire basculer au statut "rembourse").
     *
     * @return AvanceSalaire[]
     */
    public function findByPaie(Paie $paie): array
    {
        return $this->findBy(['paie' => $paie]);
    }

    /** @return AvanceSalaire[] */
    public function findByEntrepriseSince(Entreprise $entreprise, \DateTimeImmutable $since, int $limit = 500): array
    {
        return $this->createQueryBuilder('a')
            ->join('a.employee', 'e')
            ->addSelect('e')
            ->where('a.entreprise = :entreprise')
            ->andWhere('a.dateDemande >= :since')
            ->setParameter('entreprise', $entreprise)
            ->setParameter('since', $since)
            ->orderBy('a.dateDemande', 'DESC')
            ->setMaxResults(max(1, $limit))
            ->getQuery()
            ->getResult();
    }
}

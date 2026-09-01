<?php
namespace App\Entity;

use App\Repository\AvanceSalaireRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AvanceSalaireRepository::class)]
class AvanceSalaire
{
    public const STATUS_DEMANDE = 'demande';
    public const STATUS_VALIDE = 'valide';
    public const STATUS_REFUSE = 'refuse';
    public const STATUS_PAYEE = 'payee';
    public const STATUS_REMBOURSE = 'rembourse';

    public const MODE_VIREMENT = 'virement';
    public const MODE_ESPECES = 'especes';
    public const MODE_MOBILE_MONEY = 'mobile_money';
    public const MODES_PAIEMENT = [
        'Virement bancaire' => self::MODE_VIREMENT,
        'Espèces' => self::MODE_ESPECES,
        'Mobile Money' => self::MODE_MOBILE_MONEY,
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $employee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column]
    private ?float $montant = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $motif = null;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUS_DEMANDE;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDemande = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateRemboursement = null;

    #[ORM\ManyToOne]
    private ?User $validePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\ManyToOne]
    private ?Paie $paie = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $modePaiement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $referencePaiement = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $datePaiement = null;

    #[ORM\ManyToOne]
    private ?User $payePar = null;

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getPaie(): ?Paie
    {
        return $this->paie;
    }

    public function setPaie(?Paie $paie): static
    {
        $this->paie = $paie;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getReferencePaiement(): ?string
    {
        return $this->referencePaiement;
    }

    public function setReferencePaiement(?string $referencePaiement): static
    {
        $this->referencePaiement = $referencePaiement;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeImmutable
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): static
    {
        $this->datePaiement = $datePaiement ? \DateTimeImmutable::createFromInterface($datePaiement) : null;
        return $this;
    }

    public function getPayePar(): ?User
    {
        return $this->payePar;
    }

    public function setPayePar(?User $payePar): static
    {
        $this->payePar = $payePar;
        return $this;
    }

    public function isPayee(): bool
    {
        return in_array($this->statut, [self::STATUS_PAYEE, self::STATUS_REMBOURSE], true);
    }

    public function isRemboursee(): bool
    {
        return $this->statut === self::STATUS_REMBOURSE;
    }

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }


    public function getId(): ?int { return $this->id; }
    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getMontant(): ?float { return $this->montant; }
    public function setMontant(float $montant): static { $this->montant = $montant; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(string $motif): static { $this->motif = $motif; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getDateDemande(): ?\DateTimeImmutable { return $this->dateDemande; }
    public function setDateDemande(?\DateTimeInterface $dateDemande): static { $this->dateDemande = $dateDemande ? \DateTimeImmutable::createFromInterface($dateDemande) : null; return $this; }
    public function getValidePar(): ?User { return $this->validePar; }
    public function setValidePar(?User $validePar): static { $this->validePar = $validePar; return $this; }
    public function getDateRemboursement(): ?\DateTimeImmutable
    {
        return $this->dateRemboursement;
    }

    public function setDateRemboursement(?\DateTimeInterface $dateRemboursement): static
    {
        $this->dateRemboursement = $dateRemboursement ? \DateTimeImmutable::createFromInterface($dateRemboursement) : null;
        return $this;
    }
}

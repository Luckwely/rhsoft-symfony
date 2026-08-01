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
    public const STATUS_REMBOURSE = 'rembourse';

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

    public function __construct()
    {
        $this->dateDemande = new \DateTimeImmutable();
        $this->createdAt = new \DateTimeImmutable();
    }

    // getters setters... générés par make:entity
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
    public function setDateDemande(\DateTimeImmutable $dateDemande): static { $this->dateDemande = $dateDemande; return $this; }
    public function getValidePar(): ?User { return $this->validePar; }
    public function setValidePar(?User $validePar): static { $this->validePar = $validePar; return $this; }
}

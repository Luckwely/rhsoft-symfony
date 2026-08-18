<?php

namespace App\Entity;

use App\Repository\CongeRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert; 

#[ORM\Entity(repositoryClass: CongeRepository::class)]
class Conge
{
    public const STATUS_DEMANDE = 'demande';
    public const STATUS_VALIDE = 'valide';
    public const STATUS_REFUSE = 'refuse';
    public const STATUS_ANNULE = 'annule';

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

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?TypeConge $typeConge = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date de début est obligatoire.")]
    #[Assert\GreaterThanOrEqual("today", message: "La date de début ne peut pas être dans le passé.")]
    private ?\DateTimeImmutable $dateDebut = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    #[Assert\NotNull(message: "La date de fin est obligatoire.")]
    #[Assert\GreaterThanOrEqual(propertyPath: "dateDebut", message: "La date de fin doit être postérieure ou égale à la date de début.")]
    private ?\DateTimeImmutable $dateFin = null;

    #[ORM\Column(nullable: true)]
    #[Assert\LessThanOrEqual(value: 30, message: "Un congé ne peut pas dépasser 30 jours (1 mois) d'affilée.")]
    private ?float $nbJours = 0;

    #[ORM\Column(length: 20)]
    private ?string $statut = self::STATUS_DEMANDE;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\ManyToOne]
    private ?User $validePar = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $valideLe = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getDuree(): ?float
    {
        return $this->nbJours;
    }

    public function setDuree(?float $duree): static
    {
        $this->nbJours = $duree;
        return $this;
    }
    // -----------------------------------------------------------------------------------------

    public function getId(): ?int { return $this->id; }
    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getTypeConge(): ?TypeConge { return $this->typeConge; }
    public function setTypeConge(?TypeConge $typeConge): static { $this->typeConge = $typeConge; return $this; }
    public function getDateDebut(): ?\DateTimeImmutable { return $this->dateDebut; }
    public function setDateDebut(?\DateTimeInterface $dateDebut): static { $this->dateDebut = $dateDebut ? \DateTimeImmutable::createFromInterface($dateDebut) : null; return $this; }
    public function getDateFin(): ?\DateTimeImmutable { return $this->dateFin; }
    public function setDateFin(?\DateTimeInterface $dateFin): static { $this->dateFin = $dateFin ? \DateTimeImmutable::createFromInterface($dateFin) : null; return $this; }
    public function getNbJours(): ?float { return $this->nbJours; }
    public function setNbJours(float $nbJours): static { $this->nbJours = $nbJours; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(?string $motif): static { $this->motif = $motif; return $this; }
    public function getValidePar(): ?User { return $this->validePar; }
    public function setValidePar(?User $validePar): static { $this->validePar = $validePar; return $this; }
    public function getValideLe(): ?\DateTimeImmutable { return $this->valideLe; }
    public function setValideLe(?\DateTimeInterface $valideLe): static { $this->valideLe = $valideLe ? \DateTimeImmutable::createFromInterface($valideLe) : null; return $this; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt ? \DateTimeImmutable::createFromInterface($createdAt) : null;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function isValide(): bool { return $this->statut === self::STATUS_VALIDE; }
}
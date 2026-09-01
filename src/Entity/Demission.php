<?php

namespace App\Entity;

use App\Repository\DemissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemissionRepository::class)]
class Demission
{
    public const STATUS_EN_ATTENTE = 'en_attente';
    public const STATUS_VALIDEE = 'validee';
    public const STATUS_REFUSEE = 'refusee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'demissions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $employee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDepart = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $dateDemande = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $motif = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = self::STATUS_EN_ATTENTE;

    #[ORM\ManyToOne]
    private ?User $validePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $valideLe = null;

    public function getId(): ?int { return $this->id; }
    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getDateDepart(): ?\DateTimeImmutable { return $this->dateDepart; }
    public function setDateDepart(?\DateTimeImmutable $dateDepart): static { $this->dateDepart = $dateDepart; return $this; }
    public function getDateDemande(): ?\DateTimeImmutable { return $this->dateDemande; }
    public function setDateDemande(?\DateTimeImmutable $dateDemande): static { $this->dateDemande = $dateDemande; return $this; }
    public function getMotif(): ?string { return $this->motif; }
    public function setMotif(string $motif): static { $this->motif = $motif; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function getValidePar(): ?User { return $this->validePar; }
    public function setValidePar(?User $validePar): static { $this->validePar = $validePar; return $this; }
    public function getValideLe(): ?\DateTimeImmutable { return $this->valideLe; }
    public function setValideLe(?\DateTimeInterface $valideLe): static { $this->valideLe = $valideLe ? \DateTimeImmutable::createFromInterface($valideLe) : null; return $this; }
}

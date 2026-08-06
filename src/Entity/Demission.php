<?php

namespace App\Entity;

use App\Repository\DemissionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DemissionRepository::class)]
class Demission
{
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
    private ?string $statut = 'en_attente';

    // Getters and Setters...
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
}

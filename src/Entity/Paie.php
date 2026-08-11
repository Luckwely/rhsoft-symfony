<?php

namespace App\Entity;

use App\Repository\PaieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PaieRepository::class)]
class Paie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'paies')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $employee = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $mois = null;

    #[ORM\Column(type: Types::INTEGER)]
    private ?int $annee = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salaireBrut = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $cotisations = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $salaireNet = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $masseSalariale = null;

    // 1. Simule getStartDate() en combinant l'année et le mois (utilisé pour IntlDateFormatter)
    public function getStartDate(): ?\DateTimeInterface
    {
        if ($this->annee && $this->mois) {
            return new \DateTimeImmutable("{$this->annee}-{$this->mois}-01");
        }
        return null;
    }

    // 2. Alias pour getGrossAmount() pointant vers salaireBrut
    public function getGrossAmount(): ?float
    {
        return $this->salaireBrut !== null ? (float) $this->salaireBrut : null;
    }

    // 3. Alias pour getDeductions() pointant vers cotisations
    public function getDeductions(): ?float
    {
        return $this->cotisations !== null ? (float) $this->cotisations : null;
    }

    // --- Remplacement de la colonne en doublon par une méthode virtuelle ---
    public function getNetAmount(): ?float
    {
        return $this->salaireNet !== null ? (float) $this->salaireNet : null;
    }

    public function setNetAmount(?float $netAmount): static
    {
        $this->salaireNet = $netAmount !== null ? (string) $netAmount : null;
        return $this;
    }
    // ---------------------------------------------------------------------

    public function getMasseSalariale(): ?float
    {
        return $this->masseSalariale;
    }

    public function setMasseSalariale(?float $masseSalariale): self
    {
        $this->masseSalariale = $masseSalariale;
        return $this;
    }

    public function getId(): ?int { return $this->id; }

    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }

    public function getMois(): ?int { return $this->mois; }
    public function setMois(int $mois): static { $this->mois = $mois; return $this; }

    public function getAnnee(): ?int { return $this->annee; }
    public function setAnnee(int $annee): static { $this->annee = $annee; return $this; }

    public function getSalaireBrut(): ?string { return $this->salaireBrut; }
    public function setSalaireBrut(?string $salaireBrut): static { $this->salaireBrut = $salaireBrut; return $this; }

    public function getCotisations(): ?string { return $this->cotisations; }
    public function setCotisations(?string $cotisations): static { $this->cotisations = $cotisations; return $this; }

    public function getSalaireNet(): ?string { return $this->salaireNet; }
    public function setSalaireNet(?string $salaireNet): static { $this->salaireNet = $salaireNet; return $this; }

    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
}

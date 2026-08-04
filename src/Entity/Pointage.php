<?php

namespace App\Entity;

use App\Repository\PointageRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PointageRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_pointage_user_date', columns: ['employee_id', 'date'])] // 1 seul pointage/jour
class Pointage
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'pointages')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $employee = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE)]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureEntree = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureSortie = null;

    #[ORM\Column(type: 'integer', options: ['default' => 60])]
    private ?int $pauseMinutes = 60;

    #[ORM\Column(length: 20, options: ['default' => 'absent'])]
    private ?string $statut = 'absent';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private ?bool $valide = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motifCorrection = null;

    #[ORM\ManyToOne] // FIX 4: 1 seul admin qui corrige
    private ?User $corrigePar = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $corrigeLe = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heurePrevueDebut = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heurePrevueFin = null;

    #[ORM\Column(type: 'integer', options: ['default' => 60])]
    private ?int $pausePrevueMinutes = 60;

    // ===== GETTERS CALCULÉS POUR LE TWIG =====
    public function getHeuresTravaillees(): float
    {
        if (!$this->heureEntree || !$this->heureSortie) return 0;
        $diff = $this->heureSortie->diff($this->heureEntree);
        $heures = $diff->h + ($diff->i / 60);
        return round(max(0, $heures - ($this->pauseMinutes / 60)), 2);
    }

    public function getHeuresSup(): float
    {
        $heuresContract = $this->employee?->getHeuresContractuelles() ?? 8.0;
        $heures = $this->getHeuresTravaillees();
        return $heures > $heuresContract ? round($heures - $heuresContract, 2) : 0;
    }

    public function getMinutesRetard(): int
    {
        if(!$this->heureEntree || !$this->heurePrevueDebut) return 0;
        $retard = $this->heureEntree->getTimestamp() - $this->heurePrevueDebut->getTimestamp();
        return $retard > 0 ? (int)($retard / 60) : 0;
    }

    public function getStatutLabel(): string
    {
        return match($this->statut) {
            'present' => 'Présent',
            'retard' => 'En Retard',
            'absent' => 'Absent',
            'conge' => 'En Congé',
            default => 'Inconnu',
        };
    }

    public function getFormattedHeuresTravaillees(): string
    {
        if (!$this->heureEntree || !$this->heureSortie) {
            return '0h 00min';
        }

        $diff = $this->heureSortie->diff($this->heureEntree);
        $heures = $diff->h;
        $minutes = $diff->i;

        if ($this->pauseMinutes) {
            $totalMinutes = ($heures * 60) + $minutes - $this->pauseMinutes;
            if ($totalMinutes < 0) {
                $totalMinutes = 0;
            }
            $heures = intdiv($totalMinutes, 60);
            $minutes = $totalMinutes % 60;
        }

        return sprintf('%dh %02dmin', $heures, $minutes);
    }

    // GETTERS SETTERS
    public function getId(): ?int { return $this->id; }
    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(\DateTimeImmutable $date): static { $this->date = $date; return $this; }
    public function getHeureEntree(): ?\DateTimeImmutable { return $this->heureEntree; }
    public function setHeureEntree(?\DateTimeImmutable $heureEntree): static { $this->heureEntree = $heureEntree; return $this; }
    public function getHeureSortie(): ?\DateTimeImmutable { return $this->heureSortie; }
    public function setHeureSortie(?\DateTimeImmutable $heureSortie): static { $this->heureSortie = $heureSortie; return $this; }
    public function getPauseMinutes(): ?int { return $this->pauseMinutes; }
    public function setPauseMinutes(int $pauseMinutes): static { $this->pauseMinutes = $pauseMinutes; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function isValide(): ?bool { return $this->valide; }
    public function setValide(bool $valide): static { $this->valide = $valide; return $this; }
    public function getCorrigePar(): ?User { return $this->corrigePar; }
    public function setCorrigePar(?User $corrigePar): static { $this->corrigePar = $corrigePar; return $this; }
    public function getHeurePrevueDebut(): ?\DateTimeImmutable { return $this->heurePrevueDebut; }
    public function setHeurePrevueDebut(?\DateTimeImmutable $heurePrevueDebut): static { $this->heurePrevueDebut = $heurePrevueDebut; return $this; }

    public function getHeurePrevueFin(): ?\DateTimeImmutable { return $this->heurePrevueFin; }
    public function setHeurePrevueFin(?\DateTimeImmutable $heurePrevueFin): static { $this->heurePrevueFin = $heurePrevueFin; return $this; }

    public function getPausePrevueMinutes(): ?int { return $this->pausePrevueMinutes; }
    public function setPausePrevueMinutes(?int $pausePrevueMinutes): static { $this->pausePrevueMinutes = $pausePrevueMinutes; return $this; }
    public function getCorrigeLe(): ?\DateTimeImmutable { return $this->corrigeLe; }
    public function setCorrigeLe(?\DateTimeImmutable $corrigeLe): static { $this->corrigeLe = $corrigeLe; return $this; }
}



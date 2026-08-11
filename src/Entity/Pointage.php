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

    #[ORM\Column(nullable: true)]
    private ?int $pauseDurationMinutes = 0;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureDebutPause = null;

    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $heureFinPause = null;

    // ===== GETTERS CALCULÉS POUR LE TWIG =====
    public function getHeuresTravaillees(): float
    {
        if (!$this->heureEntree || !$this->heureSortie) {
            return 0.0;
        }

        $diffSeconds = $this->heureSortie->getTimestamp() - $this->heureEntree->getTimestamp();
        $pauseMinutes = $this->pauseDurationMinutes ?? $this->pauseMinutes ?? 0;
        $totalMinutes = max(0, (int)($diffSeconds / 60) - $pauseMinutes);

        return round($totalMinutes / 60, 2);
    }

    public function getHeuresSup(): float
    {
        // Make sure your User entity has this method, or fall back safely to 8.0
        $heuresContract = method_exists($this->employee, 'getHeuresContractuelles')
            ? ($this->employee->getHeuresContractuelles() ?? 8.0)
            : 8.0;

        $heures = $this->getHeuresTravaillees();
        return $heures > $heuresContract ? round($heures - $heuresContract, 2) : 0.0;
    }

    public function getMinutesRetard(): int
    {
        if (!$this->heureEntree || !$this->heurePrevueDebut) {
            return 0;
        }

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

        $diffSeconds = $this->heureSortie->getTimestamp() - $this->heureEntree->getTimestamp();
        $totalMinutes = max(0, (int)($diffSeconds / 60) - $this->pauseMinutes);

        $heures = intdiv($totalMinutes, 60);
        $minutes = $totalMinutes % 60;

        return sprintf('%dh %02dmin', $heures, $minutes);
    }

    // GETTERS SETTERS
    public function getHeureDebutPause(): ?\DateTimeImmutable { return $this->heureDebutPause; }
    public function setHeureDebutPause(?\DateTimeInterface $heureDebutPause): static { $this->heureDebutPause = $heureDebutPause ? \DateTimeImmutable::createFromInterface($heureDebutPause) : null; return $this; }

    public function getHeureFinPause(): ?\DateTimeImmutable { return $this->heureFinPause; }
    public function setHeureFinPause(?\DateTimeInterface $heureFinPause): static { $this->heureFinPause = $heureFinPause ? \DateTimeImmutable::createFromInterface($heureFinPause) : null; return $this; }

    // Helper pour savoir si l'employé est actuellement en pause
    public function isOnPause(): bool
    {
        return $this->heureDebutPause !== null && $this->heureFinPause === null;
    }
    public function getId(): ?int { return $this->id; }
    public function getEmployee(): ?User { return $this->employee; }
    public function setEmployee(?User $employee): static { $this->employee = $employee; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getDate(): ?\DateTimeImmutable { return $this->date; }
    public function setDate(?\DateTimeInterface $date): static { $this->date = $date ? \DateTimeImmutable::createFromInterface($date) : null; return $this; }
    public function getHeureEntree(): ?\DateTimeImmutable { return $this->heureEntree; }
    public function setHeureEntree(?\DateTimeInterface $heureEntree): static { $this->heureEntree = $heureEntree ? \DateTimeImmutable::createFromInterface($heureEntree) : null; return $this; }
    public function getHeureSortie(): ?\DateTimeImmutable { return $this->heureSortie; }
    public function setHeureSortie(?\DateTimeInterface $heureSortie): static { $this->heureSortie = $heureSortie ? \DateTimeImmutable::createFromInterface($heureSortie) : null; return $this; }
    public function getPauseMinutes(): ?int { return $this->pauseMinutes; }
    public function setPauseMinutes(int $pauseMinutes): static { $this->pauseMinutes = $pauseMinutes; return $this; }
    public function getStatut(): ?string { return $this->statut; }
    public function setStatut(string $statut): static { $this->statut = $statut; return $this; }
    public function isValide(): ?bool { return $this->valide; }
    public function setValide(bool $valide): static { $this->valide = $valide; return $this; }
    public function getCorrigePar(): ?User { return $this->corrigePar; }
    public function setCorrigePar(?User $corrigePar): static { $this->corrigePar = $corrigePar; return $this; }
    public function getHeurePrevueDebut(): ?\DateTimeImmutable { return $this->heurePrevueDebut; }
    public function setHeurePrevueDebut(?\DateTimeInterface $heurePrevueDebut): static { $this->heurePrevueDebut = $heurePrevueDebut ? \DateTimeImmutable::createFromInterface($heurePrevueDebut) : null; return $this; }

    public function getHeurePrevueFin(): ?\DateTimeImmutable { return $this->heurePrevueFin; }
    public function setHeurePrevueFin(?\DateTimeInterface $heurePrevueFin): static { $this->heurePrevueFin = $heurePrevueFin ? \DateTimeImmutable::createFromInterface($heurePrevueFin) : null; return $this; }

    public function getPausePrevueMinutes(): ?int { return $this->pausePrevueMinutes; }
    public function setPausePrevueMinutes(?int $pausePrevueMinutes): static { $this->pausePrevueMinutes = $pausePrevueMinutes; return $this; }

    public function getPauseDurationMinutes(): ?int { return $this->pauseDurationMinutes; }
    public function setPauseDurationMinutes(?int $pauseDurationMinutes): static { $this->pauseDurationMinutes = $pauseDurationMinutes; return $this; }

    public function getCorrigeLe(): ?\DateTimeImmutable { return $this->corrigeLe; }
    public function setCorrigeLe(?\DateTimeInterface $corrigeLe): static { $this->corrigeLe = $corrigeLe ? \DateTimeImmutable::createFromInterface($corrigeLe) : null; return $this; }
    public function setMotifCorrection(?string $motifCorrection): static
    {
        $this->motifCorrection = $motifCorrection;
        return $this;
    }
}



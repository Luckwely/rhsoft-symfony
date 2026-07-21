<?php

namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
class Planning
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'plannings')] // 1. CORRIGE ICI
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 20)] // 'lundi', 'mardi'...
    private ?string $dayOfWeek = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $weekStart = null; // Lundi de la semaine

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pause = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $pausette = null;

    #[ORM\Column] // 2. CORRIGE ICI: boolean
    private ?bool $isDayOff = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)] // 3. CORRIGE ICI
    private ?string $comment = null;

    // GETTERS SETTERS
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }

    public function getDayOfWeek(): ?string { return $this->dayOfWeek; }
    public function setDayOfWeek(string $dayOfWeek): static { $this->dayOfWeek = $dayOfWeek; return $this; }

    public function getWeekStart(): ?\DateTimeInterface { return $this->weekStart; }
    public function setWeekStart(\DateTimeInterface $weekStart): static { $this->weekStart = $weekStart; return $this; }

    public function getHeureDebut(): ?\DateTimeInterface { return $this->heureDebut; } // 4. CORRIGE ICI
    public function setHeureDebut(\DateTimeInterface $heureDebut): static { $this->heureDebut = $heureDebut; return $this; }

    public function getHeureFin(): ?\DateTimeInterface { return $this->heureFin; } // 4. CORRIGE ICI
    public function setHeureFin(\DateTimeInterface $heureFin): static { $this->heureFin = $heureFin; return $this; }

    public function getPause(): ?\DateTimeInterface { return $this->pause; } // 4. CORRIGE ICI
    public function setPause(?\DateTimeInterface $pause): static { $this->pause = $pause; return $this; }

    public function getPausette(): ?\DateTimeInterface { return $this->pausette; } // 4. CORRIGE ICI
    public function setPausette(?\DateTimeInterface $pausette): static { $this->pausette = $pausette; return $this; }

    public function isDayOff(): ?bool { return $this->isDayOff; } // Convention pour boolean: isDayOff
    public function setDayOff(bool $isDayOff): static { $this->isDayOff = $isDayOff; return $this; }

    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }
}

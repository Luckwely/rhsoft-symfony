<?php
namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_planning_per_day', columns: ['user_id', 'week_start', 'day_of_week'])]
class Planning
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'plannings')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(length: 20)]
    private ?string $dayOfWeek = null;

    #[ORM\Column(name: 'week_start', type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $weekStart = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureDebut = null;

    #[ORM\Column(type: Types::TIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $heureFin = null;

    #[ORM\Column(nullable: true)]
    private ?int $pauseMinutes = null;

    #[ORM\Column]
    private ?bool $isDayOff = false;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $comment = null;

    // ===== POUR HISTORIQUE =====
    #[ORM\Column(length: 20)]
    private ?string $status = 'brouillon'; // brouillon, valide, archive

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $validatedAt = null; // Quand on "valide" la semaine

    #[ORM\ManyToOne] // Qui a validé
    private ?User $validatedBy = null;

    public function __construct() {
        $this->createdAt = new \DateTimeImmutable();
    }

    // GETTERS SETTERS...
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getDayOfWeek(): ?string { return $this->dayOfWeek; }
    public function setDayOfWeek(string $dayOfWeek): static { $this->dayOfWeek = $dayOfWeek; return $this; }
    public function getWeekStart(): ?\DateTimeInterface { return $this->weekStart; }
    public function setWeekStart(\DateTimeInterface $weekStart): static { $this->weekStart = $weekStart; return $this; }
    public function getHeureDebut(): ?\DateTimeInterface { return $this->heureDebut; }
    public function setHeureDebut(?\DateTimeInterface $heureDebut): static { $this->heureDebut = $heureDebut; return $this; }
    public function getHeureFin(): ?\DateTimeInterface { return $this->heureFin; }
    public function setHeureFin(?\DateTimeInterface $heureFin): static { $this->heureFin = $heureFin; return $this; }
    public function getPauseMinutes(): ?int { return $this->pauseMinutes; }
    public function setPauseMinutes(?int $pauseMinutes): static { $this->pauseMinutes = $pauseMinutes; return $this; }
    public function isDayOff(): ?bool { return $this->isDayOff; }
    public function setDayOff(bool $isDayOff): static { $this->isDayOff = $isDayOff; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeImmutable $validatedAt): static { $this->validatedAt = $validatedAt; return $this; }
    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }
}

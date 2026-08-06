<?php
namespace App\Entity;

use App\Repository\PlanningRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: PlanningRepository::class)]
#[ORM\UniqueConstraint(name: 'unique_planning_per_day', columns: ['user_id', 'week_start', 'day_of_week'])]
class Planning
{
    public const TYPE_TRAVAIL = 'travail';
    public const TYPE_REPOS = 'repos';
    public const TYPE_CONGE = 'conge';
    public const TYPE_FERIE = 'ferie';
    public const STATUT_BROUILLON = 'brouillon';
    public const STATUT_VALIDE = 'valide';
    public const STATUT_ARCHIVE = 'archive';

    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'plannings')] #[ORM\JoinColumn(nullable: false)] private ?User $user = null;
    #[ORM\ManyToOne(inversedBy: 'plannings')] #[ORM\JoinColumn(nullable: false)] private ?Entreprise $entreprise = null;
    #[ORM\Column(length: 20)] private ?string $dayOfWeek = null;
    #[ORM\Column(name: 'week_start', type: Types::DATE_IMMUTABLE)] private ?\DateTimeInterface $weekStart = null;
    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)] private ?\DateTimeInterface $heureDebut = null;
    #[ORM\Column(type: Types::TIME_IMMUTABLE, nullable: true)] private ?\DateTimeInterface $heureFin = null;
    #[ORM\Column(nullable: true)] private ?int $pauseMinutes = null;
    #[ORM\Column(length: 20)] private ?string $typeJour = self::TYPE_TRAVAIL;
    #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $comment = null;
    #[ORM\Column(length: 20)] private ?string $status = self::STATUT_BROUILLON;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private ?\DateTimeImmutable $createdAt = null;
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)] private ?\DateTimeImmutable $validatedAt = null;
    #[ORM\ManyToOne] private ?User $validatedBy = null;

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    // HELPERS
    public function isTravail(): bool { return $this->typeJour === self::TYPE_TRAVAIL; }
    public function isDayOff(): bool { return $this->typeJour === self::TYPE_REPOS; }
    public function isConge(): bool { return $this->typeJour === self::TYPE_CONGE; }
    public function isFerie(): bool { return $this->typeJour === self::TYPE_FERIE; }

    public function setDayOff(bool $dayOff): static
    {
        $this->typeJour = $dayOff ? self::TYPE_REPOS : self::TYPE_TRAVAIL;
        return $this;
    }

    public function getDureeMinutes(): int
    {
        if(!$this->isTravail() || !$this->heureDebut || !$this->heureFin) return 0;
        $diff = ($this->heureFin->getTimestamp() - $this->heureDebut->getTimestamp()) / 60;
        return max(0, $diff - ($this->pauseMinutes ?? 0));
    }

    #[Assert\Callback]
    public function validate(ExecutionContextInterface $context): void
    {
        if ($this->typeJour !== self::TYPE_TRAVAIL && ($this->heureDebut || $this->heureFin)) {
            $context->buildViolation('Un jour de repos/congé/férié ne doit pas avoir d\'horaires')
                ->atPath('heureDebut')->addViolation();
        }
    }

    // GETTERS SETTERS
    public function getId(): ?int { return $this->id; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $user): static { $this->user = $user; return $this; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getDayOfWeek(): ?string { return $this->dayOfWeek; }
    public function setDayOfWeek(string $dayOfWeek): static { $this->dayOfWeek = $dayOfWeek; return $this; }
    public function getWeekStart(): ?\DateTimeInterface { return $this->weekStart; }
    public function setWeekStart(?\DateTimeInterface $weekStart): static { $this->weekStart = $weekStart ? \DateTimeImmutable::createFromInterface($weekStart) : null; return $this; }
    public function getHeureDebut(): ?\DateTimeInterface { return $this->heureDebut; }
    public function setHeureDebut(?\DateTimeInterface $heureDebut): static { $this->heureDebut = $heureDebut ? \DateTimeImmutable::createFromInterface($heureDebut) : null; return $this; }
    public function getHeureFin(): ?\DateTimeInterface { return $this->heureFin; }
    public function setHeureFin(?\DateTimeInterface $heureFin): static { $this->heureFin = $heureFin ? \DateTimeImmutable::createFromInterface($heureFin) : null; return $this; }
    public function getPauseMinutes(): ?int { return $this->pauseMinutes; }
    public function setPauseMinutes(?int $pauseMinutes): static { $this->pauseMinutes = $pauseMinutes; return $this; }
    public function getTypeJour(): ?string { return $this->typeJour; }
    public function setTypeJour(string $typeJour): static { $this->typeJour = $typeJour; return $this; }
    public function getComment(): ?string { return $this->comment; }
    public function setComment(?string $comment): static { $this->comment = $comment; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(?\DateTimeInterface $createdAt): static { $this->createdAt = $createdAt ? \DateTimeImmutable::createFromInterface($createdAt) : null; return $this; }
    public function getValidatedAt(): ?\DateTimeImmutable { return $this->validatedAt; }
    public function setValidatedAt(?\DateTimeInterface $validatedAt): static { $this->validatedAt = $validatedAt ? \DateTimeImmutable::createFromInterface($validatedAt) : null; return $this; }
    public function getValidatedBy(): ?User { return $this->validatedBy; }
    public function setValidatedBy(?User $validatedBy): static { $this->validatedBy = $validatedBy; return $this; }
}

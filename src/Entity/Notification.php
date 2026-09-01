<?php

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;


#[ORM\Entity(repositoryClass: NotificationRepository::class)]
class Notification
{
    public const TYPE_AVANCE_SOUMISE = 'avance_soumise';
    public const TYPE_AVANCE_APPROUVEE = 'avance_approuvee';
    public const TYPE_AVANCE_REJETEE = 'avance_rejetee';
    public const TYPE_AVANCE_PAYEE = 'avance_payee';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $destinataire = null;

    #[ORM\Column(length: 40)]
    private ?string $type = null;

    #[ORM\Column(length: 150)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $message = null;


    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lien = null;

    #[ORM\Column]
    private bool $lue = false;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getDestinataire(): ?User { return $this->destinataire; }
    public function setDestinataire(?User $destinataire): static { $this->destinataire = $destinataire; return $this; }

    public function getType(): ?string { return $this->type; }
    public function setType(string $type): static { $this->type = $type; return $this; }

    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }

    public function getMessage(): ?string { return $this->message; }
    public function setMessage(string $message): static { $this->message = $message; return $this; }

    public function getLien(): ?string { return $this->lien; }
    public function setLien(?string $lien): static { $this->lien = $lien; return $this; }

    public function isLue(): bool { return $this->lue; }
    public function setLue(bool $lue): static { $this->lue = $lue; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function setCreatedAt(\DateTimeImmutable $createdAt): static { $this->createdAt = $createdAt; return $this; }
}

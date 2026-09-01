<?php namespace App\Entity;

use App\Repository\CandidatureRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CandidatureRepository::class)]
class Candidature
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column] private ?int $id = null;
    #[ORM\ManyToOne(inversedBy: 'candidatures')] #[ORM\JoinColumn(nullable: false)] private ?Offre $offre = null;
    #[ORM\Column(length: 255)] private ?string $nom = null;
    #[ORM\Column(length: 255, options: ['default' => ''])] private ?string $prenom = null;
    #[ORM\Column(length: 255)] private ?string $email = null;
    #[ORM\Column(length: 255, nullable: true)] private ?string $cv = null;
    #[ORM\Column(length: 20, options: ['default' => 'site'])] private ?string $source = 'site';
    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)] private ?\DateTimeImmutable $createdAt = null;
    #[ORM\Column(length: 255)] private ?string $telephone = null;
    #[ORM\Column(type: Types::TEXT, nullable: true)] private ?string $lettreMotivation = null;

    #[ORM\Column(length: 20, options: ['default' => 'en_attente'])]
    private string $statut = 'en_attente';

    public function __construct() { $this->createdAt = new \DateTimeImmutable(); }

    public function getStatut(): string { return $this->statut; } // <- GARDE QUE CELUI-LA
    public function setStatut(string $statut): self { $this->statut = $statut; return $this; }

    public function getId(): ?int { return $this->id; }
    public function getOffre(): ?Offre { return $this->offre; }
    public function setOffre(?Offre $offre): static { $this->offre = $offre; return $this; }
    public function getNom(): ?string { return $this->nom; }
    public function setNom(string $nom): static { $this->nom = $nom; return $this; }
    public function getPrenom(): ?string { return $this->prenom; }
    public function setPrenom(string $prenom): static { $this->prenom = $prenom; return $this; }
    public function getNomComplet(): string { return trim($this->prenom . ' ' . $this->nom); }
    public function getEmail(): ?string { return $this->email; }
    public function setEmail(string $email): static { $this->email = $email; return $this; }
    public function getCv(): ?string { return $this->cv; }
    public function setCv(string $cv): static { $this->cv = $cv; return $this; }
    public function getSource(): ?string { return $this->source; }
    public function setSource(string $source): static { $this->source = $source; return $this; }
    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; }
    public function getTelephone(): ?string { return $this->telephone; }
    public function setTelephone(string $telephone): static { $this->telephone = $telephone; return $this; }
    public function getLettreMotivation(): ?string { return $this->lettreMotivation; }
    public function setLettreMotivation(string $lettreMotivation): static { $this->lettreMotivation = $lettreMotivation; return $this; }
}

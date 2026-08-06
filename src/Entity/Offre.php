<?php
namespace App\Entity;

use App\Repository\OffreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OffreRepository::class)]
class Offre
{
    #[ORM\Id] #[ORM\GeneratedValue] #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'offres')] #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 20, options: ['default' => 'ouverte'])]
    private ?string $status = 'ouverte';

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dateExpiration = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\OneToMany(targetEntity: Candidature::class, mappedBy: 'offre')]
    private Collection $candidatures;

    public function __construct()
    {
        $this->candidatures = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getEntreprise(): ?Entreprise { return $this->entreprise; }
    public function setEntreprise(?Entreprise $entreprise): static { $this->entreprise = $entreprise; return $this; }
    public function getTitre(): ?string { return $this->titre; }
    public function setTitre(string $titre): static { $this->titre = $titre; return $this; }
    public function getDescription(): ?string { return $this->description; }
    public function setDescription(string $description): static { $this->description = $description; return $this; }
    public function getStatus(): ?string { return $this->status; }
    public function setStatus(string $status): static { $this->status = $status; return $this; }

    public function getDateExpiration(): ?\DateTimeImmutable { return $this->dateExpiration; }
    public function setDateExpiration(?\DateTimeInterface $dateExpiration): static { $this->dateExpiration = $dateExpiration ? \DateTimeImmutable::createFromInterface($dateExpiration) : null; return $this; }

    public function getCreatedAt(): ?\DateTimeImmutable { return $this->createdAt; } // <-- OK MAINTENANT

    /** * @return Collection<int, Candidature> */
    public function getCandidatures(): Collection { return $this->candidatures; }

    public function addCandidature(Candidature $candidature): static
    {
        if (!$this->candidatures->contains($candidature)) {
            $this->candidatures->add($candidature);
            $candidature->setOffre($this);
        }
        return $this;
    }

    public function removeCandidature(Candidature $candidature): static
    {
        if ($this->candidatures->removeElement($candidature)) {
            if ($candidature->getOffre() === $this) {
                $candidature->setOffre(null);
            }
        }
        return $this;
    }
}

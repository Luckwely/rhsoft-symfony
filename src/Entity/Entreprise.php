<?php

namespace App\Entity;

use App\Repository\EntrepriseRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntrepriseRepository::class)]
class Entreprise
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $nif = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $logo = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $tel = null;

    #[ORM\Column(length: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 20)]
    private ?string $status = null;

    #[ORM\Column(length: 20)]
    private ?string $plan = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $date_fin_abonnement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripe_customer_id = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $stripe_subscription_id = null;

    #[ORM\Column(length: 255)]
    private ?string $slug = null;

    #[ORM\Column]
    private ?\DateTime $created_at = null;

    /**
     * @var Collection<int, User>
     */
    #[ORM\OneToMany(targetEntity: User::class, mappedBy: 'entreprise')]
    private Collection $users;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    private ?float $prixMois = 0;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $modulePaie = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $modulePointage = false;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $moduleRh = false;

    #[ORM\Column(type: 'integer', options: ['default' => 15])]
    private ?int $toleranceRetard = 15;

    #[ORM\Column(type: 'integer', options: ['default' => 50])]
    private ?int $plafondAvancePourcentage = 50;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 1000000])]
    private ?float $salaireBaseAdmin = 1000000;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 800000])]
    private ?float $salaireBaseRh = 800000;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 600000])]
    private ?float $salaireBaseManager = 600000;

    #[ORM\Column(type: 'decimal', precision: 12, scale: 2, options: ['default' => 400000])]
    private ?float $salaireBaseEmploye = 400000;

    /**
     * @var Collection<int, Conge>
     */
    #[ORM\OneToMany(targetEntity: Conge::class, mappedBy: 'entreprise')]
    private Collection $typeConge;

    /**
     * @var Collection<int, Offre>
     */
    #[ORM\OneToMany(targetEntity: Offre::class, mappedBy: 'entreprise')]
    private Collection $offres;

    public function __construct()
    {
        $this->users = new ArrayCollection();
        $this->typeConge = new ArrayCollection();
        $this->offres = new ArrayCollection();
    }

    public function getToleranceRetard(): ?int
    {
        return $this->toleranceRetard;
    }

    public function setToleranceRetard(?int $toleranceRetard): static
    {
        $this->toleranceRetard = $toleranceRetard;
        return $this;
    }

    public function getPlafondAvancePourcentage(): ?int
    {
        return $this->plafondAvancePourcentage;
    }

    public function setPlafondAvancePourcentage(?int $plafondAvancePourcentage): static
    {
        $this->plafondAvancePourcentage = $plafondAvancePourcentage;
        return $this;
    }

    public function getSalaireBaseAdmin(): ?float { return $this->salaireBaseAdmin; }
    public function setSalaireBaseAdmin(?float $salaireBaseAdmin): static { $this->salaireBaseAdmin = $salaireBaseAdmin; return $this; }

    public function getSalaireBaseRh(): ?float { return $this->salaireBaseRh; }
    public function setSalaireBaseRh(?float $salaireBaseRh): static { $this->salaireBaseRh = $salaireBaseRh; return $this; }

    public function getSalaireBaseManager(): ?float { return $this->salaireBaseManager; }
    public function setSalaireBaseManager(?float $salaireBaseManager): static { $this->salaireBaseManager = $salaireBaseManager; return $this; }

    public function getSalaireBaseEmploye(): ?float { return $this->salaireBaseEmploye; }
    public function setSalaireBaseEmploye(?float $salaireBaseEmploye): static { $this->salaireBaseEmploye = $salaireBaseEmploye; return $this; }

    /**
     * @param string[] $roles
     */
    public function getSalaireBaseForRoles(array $roles): float
    {
        if (in_array('ROLE_ADMIN', $roles, true)) {
            return $this->salaireBaseAdmin ?? 1000000.0;
        }
        if (in_array('ROLE_RH', $roles, true)) {
            return $this->salaireBaseRh ?? 800000.0;
        }
        if (in_array('ROLE_MANAGER', $roles, true)) {
            return $this->salaireBaseManager ?? 600000.0;
        }

        return $this->salaireBaseEmploye ?? 400000.0;
    }

    public function isModulePaie(): bool {
        return $this->modulePaie;
    }
    public function setModulePaie(bool $modulePaie): static {
        $this->modulePaie = $modulePaie; return $this;
    }

    public function isModulePointage(): bool {
        return $this->modulePointage;
    }
    public function setModulePointage(bool $modulePointage): static {
        $this->modulePointage = $modulePointage; return $this;
    }

    public function isModuleRh(): bool {
        return $this->moduleRh;
    }
    public function setModuleRh(bool $moduleRh): static {
        $this->moduleRh = $moduleRh; return $this;
    }

    public function getPrixMois(): ?float {
        return $this->prixMois;
    }

    public function setPrixMois(?float $prixMois): static {
        $this->prixMois = $prixMois; return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;

        return $this;
    }

    public function getNif(): ?string
    {
        return $this->nif;
    }

    public function setNif(?string $nif): static
    {
        $this->nif = $nif;

        return $this;
    }

    public function getLogo(): ?string
    {
        return $this->logo;
    }

    public function setLogo(?string $logo): static
    {
        $this->logo = $logo;

        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;

        return $this;
    }

    public function getTel(): ?string
    {
        return $this->tel;
    }

    public function setTel(?string $tel): static
    {
        $this->tel = $tel;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPlan(): ?string
    {
        return $this->plan;
    }

    public function setPlan(string $plan): static
    {
        $this->plan = $plan;

        return $this;
    }

    public function getDateFinAbonnement(): ?\DateTime
    {
        return $this->date_fin_abonnement;
    }

    public function setDateFinAbonnement(?\DateTime $date_fin_abonnement): static
    {
        $this->date_fin_abonnement = $date_fin_abonnement;

        return $this;
    }

    public function getStripeCustomerId(): ?string
    {
        return $this->stripe_customer_id;
    }

    public function setStripeCustomerId(?string $stripe_customer_id): static
    {
        $this->stripe_customer_id = $stripe_customer_id;

        return $this;
    }

    public function getStripeSubscriptionId(): ?string
    {
        return $this->stripe_subscription_id;
    }

    public function setStripeSubscriptionId(?string $stripe_subscription_id): static
    {
        $this->stripe_subscription_id = $stripe_subscription_id;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTime $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    /**
     * @return Collection<int, User>
     */
    public function getUsers(): Collection
    {
        return $this->users;
    }

    public function addUser(User $user): static
    {
        if (!$this->users->contains($user)) {
            $this->users->add($user);
            $user->setEntreprise($this);
        }

        return $this;
    }

    public function removeUser(User $user): static
    {
        if ($this->users->removeElement($user)) {

            if ($user->getEntreprise() === $this) {
                $user->setEntreprise(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Conge>
     */
    public function getTypeConge(): Collection
    {
        return $this->typeConge;
    }

    public function addTypeConge(Conge $typeConge): static
    {
        if (!$this->typeConge->contains($typeConge)) {
            $this->typeConge->add($typeConge);
            $typeConge->setEntreprise($this);
        }

        return $this;
    }

    public function removeTypeConge(Conge $typeConge): static
    {
        if ($this->typeConge->removeElement($typeConge)) {

            if ($typeConge->getEntreprise() === $this) {
                $typeConge->setEntreprise(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Offre>
     */
    public function getOffres(): Collection
    {
        return $this->offres;
    }

    public function addOffre(Offre $offre): static
    {
        if (!$this->offres->contains($offre)) {
            $this->offres->add($offre);
            $offre->setEntreprise($this);
        }

        return $this;
    }

    public function removeOffre(Offre $offre): static
    {
        if ($this->offres->removeElement($offre)) {

            if ($offre->getEntreprise() === $this) {
                $offre->setEntreprise(null);
            }
        }

        return $this;
    }

}

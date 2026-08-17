<?php

namespace App\Entity;
use App\Entity\Planning;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\DBAL\Types\Types;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'There is already an account with this email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    /** * @var Collection<int, Planning> */
    #[ORM\OneToMany(targetEntity: Planning::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $plannings;

    #[ORM\Column(length: 180)]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\ManyToOne(inversedBy: 'users')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Entreprise $entreprise = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $adresse = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $photo = null; // on stocke juste le nom du fichier

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $cv = null; // pdf

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column]
    private ?bool $is_active = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $email_verified_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $last_login_at = null;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $invitationToken = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $invitationExpiresAt = null;

    #[ORM\Column]
    private bool $firstLogin = true;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $banqueNom = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $banqueIban = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $banqueRib = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEmbauche = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $soldeConge = 25;

    /**
     * @var Collection<int, Pointage>
     */
    #[ORM\OneToMany(targetEntity: Pointage::class, mappedBy: 'employee')]
    private Collection $pointages;

    /**
     * @var Collection<int, Pointage>
     */
    #[ORM\ManyToMany(targetEntity: Pointage::class, mappedBy: 'corrigePar')]
    private Collection $CorrigePar;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $poste = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $service = null;

    #[ORM\Column(type: 'decimal', precision: 4, scale: 2, options: ['default' => 8.0])]
    private ?float $heuresContractuelles = 8.0;

    /**
     * @var Collection<int, Conge>
     */
    #[ORM\OneToMany(targetEntity: Conge::class, mappedBy: 'employee')]
    private Collection $conges;

    /**
     * @var Collection<int, Demission>
     */
    #[ORM\OneToMany(targetEntity: Demission::class, mappedBy: 'employee')]
    private Collection $demissions;

    /**
     * @var Collection<int, Paie>
     */
    #[ORM\OneToMany(targetEntity: Paie::class, mappedBy: 'employee')]
    private Collection $paies;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateSortie = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $motifSortie = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $salaireBase = null;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private ?float $panierRepas = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private ?float $transport = 0;

    #[ORM\Column(type: 'float', options: ['default' => 0])]
    private ?float $anciennete = 0;

    #[ORM\Column(type: 'integer', options: ['default' => 0])]
    private ?int $personnesACharge = 0;

    public function getSalaireBase(): ?float { return $this->salaireBase; }
    public function setSalaireBase(?float $salaireBase): static { $this->salaireBase = $salaireBase; return $this; }

    public function getPanierRepas(): ?float { return $this->panierRepas; }
    public function setPanierRepas(?float $panierRepas): static { $this->panierRepas = $panierRepas; return $this; }

    public function getTransport(): ?float { return $this->transport; }
    public function setTransport(?float $transport): static { $this->transport = $transport; return $this; }

    public function getAnciennete(): ?float { return $this->anciennete; }
    public function setAnciennete(?float $anciennete): static { $this->anciennete = $anciennete; return $this; }

    public function getPersonnesACharge(): ?int { return $this->personnesACharge; }
    public function setPersonnesACharge(?int $personnesACharge): static { $this->personnesACharge = $personnesACharge; return $this; }

    // GETTERS SETTERS
    public function getPoste(): ?string { return $this->poste; }
    public function setPoste(?string $poste): static { $this->poste = $poste; return $this; }

    public function getService(): ?string { return $this->service; }
    public function setService(?string $service): static { $this->service = $service; return $this; }

    public function getHeuresContractuelles(): ?float { return $this->heuresContractuelles; }
    public function setHeuresContractuelles(?float $heuresContractuelles): static { $this->heuresContractuelles = $heuresContractuelles; return $this; }

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->plannings = new ArrayCollection();
        $this->pointages = new ArrayCollection();
        $this->CorrigePar = new ArrayCollection();
        $this->conges = new ArrayCollection();
        $this->demissions = new ArrayCollection();
        $this->paies = new ArrayCollection();
    }


    public function getDateSortie(): ?\DateTimeInterface
    {
        return $this->dateSortie;
    }

    public function setDateSortie(?\DateTimeInterface $dateSortie): static
    {
        $this->dateSortie = $dateSortie;
        return $this;
    }

    public function getMotifSortie(): ?string
    {
        return $this->motifSortie;
    }

    public function setMotifSortie(?string $motifSortie): static
    {
        $this->motifSortie = $motifSortie;
        return $this;
    }

    public function getPlannings(): Collection
    {
        return $this->plannings;
    }

    public function addPlanning(Planning $planning): static
    {
        if (!$this->plannings->contains($planning)) {
            $this->plannings->add($planning);
            $planning->setUser($this);
        }
        return $this;
    }

    public function removePlanning(Planning $planning): static
    {
        if ($this->plannings->removeElement($planning)) {
            if ($planning->getUser() === $this) {
                $planning->setUser(null);
            }
        }
        return $this;
    }

    public function getDateEmbauche(): ?\DateTimeInterface {
        return $this->dateEmbauche;
    }

    public function setDateEmbauche(?\DateTimeInterface $dateEmbauche): self {
        $this->dateEmbauche = $dateEmbauche; return $this;
    }

    public function getSoldeConge(): ?float {
        return $this->soldeConge;
    }

    public function setSoldeConge(?float $soldeConge): self {
        $this->soldeConge = $soldeConge; return $this;
    }

    public function isFirstLogin(): bool
    {
        return $this->firstLogin;
    }

    public function setFirstLogin(bool $firstLogin): static
    {
        $this->firstLogin = $firstLogin;
        return $this;
    }

    public function getId(): ?int
    {
        return $this->id;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     */


    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getRoles(): array
    {
            $roles = $this->roles;
            // guarantee every user at least has ROLE_USER
            $roles[] = 'ROLE_USER';

            return array_unique($roles);
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0".self::class."\0password"] = $this->password ? hash('crc32c', $this->password) : null;
        return $data;
    }

    public function getEntreprise(): ?Entreprise
    {
        return $this->entreprise;
    }

    public function setEntreprise(?Entreprise $entreprise): static
    {
        $this->entreprise = $entreprise;

        return $this;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getPhoto(): ?string
    {
        return $this->photo;
    }

    public function setPhoto(?string $photo): static
    {
        $this->photo = $photo;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->is_active;
    }

    public function setIsActive(bool $is_active): static
    {
        $this->is_active = $is_active;

        return $this;
    }

    public function getEmailVerifiedAt(): ?\DateTime
    {
        return $this->email_verified_at;
    }

    public function setEmailVerifiedAt(?\DateTime $email_verified_at): static
    {
        $this->email_verified_at = $email_verified_at;

        return $this;
    }

    public function getLastLoginAt(): ?\DateTime
    {
        return $this->last_login_at;
    }

    public function setLastLoginAt(?\DateTime $last_login_at): static
    {
        $this->last_login_at = $last_login_at;

        return $this;
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->is_active === true && $this->password !== null;
    }

    public function getInvitationToken(): ?string {
        return $this->invitationToken;
    }

    public function setInvitationToken(?string $invitationToken): static {
        $this->invitationToken = $invitationToken; return $this;
    }


    public function getInvitationExpiresAt(): ?\DateTimeImmutable {
        return $this->invitationExpiresAt;
        }

    public function setInvitationExpiresAt(?\DateTimeInterface $invitationExpiresAt): static {
        $this->invitationExpiresAt = $invitationExpiresAt ? \DateTimeImmutable::createFromInterface($invitationExpiresAt) : null; return $this;
    }

    public function getAdresse(): ?string {
        return $this->adresse;
    }

    public function setAdresse(?string $adresse): static {
        $this->adresse = $adresse; return $this;
    }

    public function getTelephone(): ?string {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static {
        $this->telephone = $telephone; return $this;
    }

    public function getCv(): ?string {
        return $this->cv;
    }

    public function setCv(?string $cv): static {
        $this->cv = $cv; return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable {
        return $this->createdAt;
    }

    public function getBanqueNom(): ?string {
        return $this->banqueNom;
    }

    public function setBanqueNom(?string $banqueNom): static {
        $this->banqueNom = $banqueNom; return $this;
    }

    public function getBanqueIban(): ?string {
        return $this->banqueIban;
    }

    public function setBanqueIban(?string $banqueIban): static {
        $this->banqueIban = $banqueIban; return $this;
    }

    public function getBanqueRib(): ?string {
        return $this->banqueRib;
    }

    public function setBanqueRib(?string $banqueRib): static {
        $this->banqueRib = $banqueRib; return $this;
    }

    /**
     * @return Collection<int, Pointage>
     */
    public function getPointages(): Collection
    {
        return $this->pointages;
    }

    public function addPointage(Pointage $pointage): static
    {
        if (!$this->pointages->contains($pointage)) {
            $this->pointages->add($pointage);
            $pointage->setEmployee($this);
        }

        return $this;
    }

    public function removePointage(Pointage $pointage): static
    {
        if ($this->pointages->removeElement($pointage)) {
            // set the owning side to null (unless already changed)
            if ($pointage->getEmployee() === $this) {
                $pointage->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Pointage>
     */
    public function getCorrigePar(): Collection
    {
        return $this->CorrigePar;
    }

    public function addCorrigePar(Pointage $corrigePar): static
    {
        if (!$this->CorrigePar->contains($corrigePar)) {
            $this->CorrigePar->add($corrigePar);
            $corrigePar->addCorrigePar($this);
        }

        return $this;
    }

    public function removeCorrigePar(Pointage $corrigePar): static
    {
        if ($this->CorrigePar->removeElement($corrigePar)) {
            $corrigePar->removeCorrigePar($this);
        }

        return $this;
    }

    /**
     * @return Collection<int, Conge>
     */
    public function getConges(): Collection
    {
        return $this->conges;
    }

    public function addConge(Conge $conge): static
    {
        if (!$this->conges->contains($conge)) {
            $this->conges->add($conge);
            $conge->setEmployee($this);
        }

        return $this;
    }

    public function removeConge(Conge $conge): static
    {
        if ($this->conges->removeElement($conge)) {
            // set the owning side to null (unless already changed)
            if ($conge->getEmployee() === $this) {
                $conge->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Demission>
     */
    public function getDemissions(): Collection
    {
        return $this->demissions;
    }

    public function addDemission(Demission $demission): static
    {
        if (!$this->demissions->contains($demission)) {
            $this->demissions->add($demission);
            $demission->setEmployee($this);
        }

        return $this;
    }

    public function removeDemission(Demission $demission): static
    {
        if ($this->demissions->removeElement($demission)) {
            if ($demission->getEmployee() === $this) {
                $demission->setEmployee(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Paie>
     */
    public function getPaies(): Collection
    {
        return $this->paies;
    }

    public function addPaie(Paie $paie): static
    {
        if (!$this->paies->contains($paie)) {
            $this->paies->add($paie);
            $paie->setEmployee($this);
        }

        return $this;
    }

    public function removePaie(Paie $paie): static
    {
        if ($this->paies->removeElement($paie)) {
            if ($paie->getEmployee() === $this) {
                $paie->setEmployee(null);
            }
        }

        return $this;
    }

}

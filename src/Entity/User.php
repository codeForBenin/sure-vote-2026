<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\Common\Collections\Collection;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[UniqueEntity(fields: ['email'], message: 'Il existe déjà un compte avec cet email')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isVerified = false;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $commune = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $arrondissement = null;

    public function getCommune(): ?string
    {
        return $this->commune;
    }

    public function setCommune(?string $commune): static
    {
        $this->commune = $commune;
        return $this;
    }

    public function getArrondissement(): ?string
    {
        return $this->arrondissement;
    }

    public function setArrondissement(?string $arrondissement): static
    {
        $this->arrondissement = $arrondissement;
        return $this;
    }

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $departement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $lieuDeResidence = null;

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(?string $departement): static
    {
        $this->departement = $departement;
        return $this;
    }

    #[ORM\ManyToMany(targetEntity: BureauDeVote::class)]
    private Collection $bureauxPreferes;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->bureauxPreferes = new ArrayCollection();
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

    public function getLieuDeResidence(): ?string
    {
        return $this->lieuDeResidence;
    }

    public function setLieuDeResidence(?string $lieuDeResidence): static
    {
        $this->lieuDeResidence = $lieuDeResidence;

        return $this;
    }

    /**
     * @return Collection<int, BureauDeVote>
     */
    public function getBureauxPreferes(): Collection
    {
        return $this->bureauxPreferes;
    }

    public function addBureauxPrefere(BureauDeVote $bureauxPreferere): static
    {
        if (!$this->bureauxPreferes->contains($bureauxPreferere)) {
            $this->bureauxPreferes->add($bureauxPreferere);
        }

        return $this;
    }

    public function removeBureauxPrefere(BureauDeVote $bureauxPreferere): static
    {
        $this->bureauxPreferes->removeElement($bureauxPreferere);

        return $this;
    }

    #[ORM\Column(length: 180)]
    #[Assert\Email]
    private ?string $email = null;

    /**
     * @var list<string> Les rôles de l'utilisateur
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string Le mot de passe haché
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $prenom = null;

    public function getId(): ?Uuid
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
     * Un identifiant visuel qui représente cet utilisateur.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return list<string>
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // garantir que chaque utilisateur a au moins ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    private ?string $plainPassword = null;

    public function getPlainPassword(): ?string
    {
        return $this->plainPassword;
    }

    public function setPlainPassword(?string $plainPassword): self
    {
        $this->plainPassword = $plainPassword;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // Si vous stockez des données temporaires ou sensibles sur l'utilisateur, effacez-les ici
        $this->plainPassword = null;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    #[ORM\ManyToOne(targetEntity: CentreDeVote::class)]
    #[ORM\JoinColumn(onDelete: 'SET NULL')]
    private ?CentreDeVote $assignedCentre = null;

    public function getAssignedCentre(): ?CentreDeVote
    {
        return $this->assignedCentre;
    }

    public function setAssignedCentre(?CentreDeVote $assignedCentre): static
    {
        $this->assignedCentre = $assignedCentre;
        return $this;
    }

    public function __toString()
    {
        return $this->getFullName();
    }

    public function getFullName(): string
    {
        return (string) $this->nom . ' ' . $this->prenom;
    }

    public function getPublicName(): string
    {
        $prenom = ucfirst(strtolower($this->prenom ?? ''));
        $nom = strtoupper(substr($this->nom ?? '', 0, 1));

        return trim($prenom . ' ' . $nom . '.') ?: 'Utilisateur Anonyme';
    }

    public function getAdresse(): string
    {
        $adresse = (string) $this->lieuDeResidence . ', ' . $this->arrondissement . ', ' . $this->commune;

        return trim(implode(" ", explode(",", $adresse))) !== '' ? $adresse : 'Non fournie';
    }
}

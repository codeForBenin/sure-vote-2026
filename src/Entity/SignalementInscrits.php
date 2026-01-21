<?php

namespace App\Entity;

use App\Repository\SignalementInscritsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

#[ORM\Entity(repositoryClass: SignalementInscritsRepository::class)]
#[Vich\Uploadable]
class SignalementInscrits
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?CentreDeVote $centreDeVote = null;

    #[ORM\Column]
    private ?int $nombreInscritsTotal = null;

    /**
     * Stocke le détail sous forme JSON : [{"bureau_id": 12, "inscrits": 350}, ...]
     */
    #[ORM\Column(type: Types::JSON)]
    private array $repartitionBureaux = [];

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $preuveImageName = null;

    #[Vich\UploadableField(mapping: 'inscription_proofs', fileNameProperty: 'preuveImageName')]
    private ?File $preuveImageFile = null;

    #[ORM\Column(length: 255)]
    private ?string $statut = 'PENDING'; // PENDING, VALIDATED, REJECTED

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $auteurNom = null; // Nom de la personne qui déclare (optionnel si anonyme)

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $assesseur = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCentreDeVote(): ?CentreDeVote
    {
        return $this->centreDeVote;
    }

    public function setCentreDeVote(?CentreDeVote $centreDeVote): static
    {
        $this->centreDeVote = $centreDeVote;

        return $this;
    }

    public function getNombreInscritsTotal(): ?int
    {
        return $this->nombreInscritsTotal;
    }

    public function setNombreInscritsTotal(int $nombreInscritsTotal): static
    {
        $this->nombreInscritsTotal = $nombreInscritsTotal;

        return $this;
    }

    public function getRepartitionBureaux(): array
    {
        return $this->repartitionBureaux;
    }

    public function setRepartitionBureaux(array $repartitionBureaux): static
    {
        $this->repartitionBureaux = $repartitionBureaux;

        return $this;
    }

    public function getPreuveImageName(): ?string
    {
        return $this->preuveImageName;
    }

    public function setPreuveImageName(?string $preuveImageName): static
    {
        $this->preuveImageName = $preuveImageName;

        return $this;
    }

    public function setPreuveImageFile(?File $preuveImageFile = null): void
    {
        $this->preuveImageFile = $preuveImageFile;

        if (null !== $preuveImageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getPreuveImageFile(): ?File
    {
        return $this->preuveImageFile;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getAuteurNom(): ?string
    {
        return $this->auteurNom;
    }

    public function setAuteurNom(?string $auteurNom): static
    {
        $this->auteurNom = $auteurNom;

        return $this;
    }

    public function getAssesseur(): ?User
    {
        return $this->assesseur;
    }

    public function setAssesseur(?User $assesseur): static
    {
        $this->assesseur = $assesseur;

        return $this;
    }
}

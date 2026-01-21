<?php

namespace App\Entity;

use App\Repository\ResultatRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;
use Vich\UploaderBundle\Mapping\Attribute as Vich;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: ResultatRepository::class)]
#[Vich\Uploadable]
#[ORM\HasLifecycleCallbacks]
class Resultat
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?BureauDeVote $bureauDeVote = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?Parti $parti = null;

    #[ORM\Column]
    private ?int $nombreVoix = null;

    #[Vich\UploadableField(mapping: 'vote_reports', fileNameProperty: 'pvImageName')]
    private ?File $pvImageFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $pvImageName = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne]
    private ?User $assesseur = null;

    #[ORM\Column]
    private bool $isValidated = false;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }
    public function getBureauDeVote(): ?BureauDeVote
    {
        return $this->bureauDeVote;
    }
    public function setBureauDeVote(?BureauDeVote $bureauDeVote): self
    {
        $this->bureauDeVote = $bureauDeVote;
        return $this;
    }
    public function getParti(): ?Parti
    {
        return $this->parti;
    }
    public function setParti(?Parti $parti): self
    {
        $this->parti = $parti;
        return $this;
    }
    public function getNombreVoix(): ?int
    {
        return $this->nombreVoix;
    }
    public function setNombreVoix(int $nombreVoix): self
    {
        $this->nombreVoix = $nombreVoix;
        return $this;
    }

    public function setPvImageFile(?File $pvImageFile = null): void
    {
        $this->pvImageFile = $pvImageFile;
        if (null !== $pvImageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getPvImageFile(): ?File
    {
        return $this->pvImageFile;
    }
    public function getPvImageName(): ?string
    {
        return $this->pvImageName;
    }
    public function setPvImageName(?string $pvImageName): self
    {
        $this->pvImageName = $pvImageName;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getAssesseur(): ?User
    {
        return $this->assesseur;
    }
    public function setAssesseur(?User $assesseur): self
    {
        $this->assesseur = $assesseur;
        return $this;
    }
    public function isIsValidated(): bool
    {
        return $this->isValidated;
    }
    public function setIsValidated(bool $isValidated): self
    {
        $this->isValidated = $isValidated;
        return $this;
    }

    #[ORM\PrePersist]
    public function setCreatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }
}

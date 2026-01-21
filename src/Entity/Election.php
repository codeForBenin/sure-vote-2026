<?php

namespace App\Entity;

use App\Repository\ElectionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ElectionRepository::class)]
class Election
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 255)]
    private ?string $nom = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $dateElection = null;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(type: 'time', nullable: true)]
    private ?\DateTimeInterface $heureFermeture = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombreInscrits = null;

    #[ORM\Column(nullable: true)]
    private ?int $siegesPourvoir = null;

    #[ORM\Column(nullable: true)]
    private ?int $nombreBureauxDeVote = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
    }

    public function getId(): ?Uuid
    {
        return $this->id;
    }
    public function getNom(): ?string
    {
        return $this->nom;
    }
    public function setNom(string $nom): self
    {
        $this->nom = $nom;
        return $this;
    }
    public function getDateElection(): ?\DateTimeImmutable
    {
        return $this->dateElection;
    }
    public function setDateElection(\DateTimeImmutable $dateElection): self
    {
        $this->dateElection = $dateElection;
        return $this;
    }
    public function isIsActive(): bool
    {
        return $this->isActive;
    }
    public function setIsActive(bool $isActive): self
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getHeureFermeture(): ?\DateTimeInterface
    {
        return $this->heureFermeture;
    }

    public function setHeureFermeture(?\DateTimeInterface $heureFermeture): self
    {
        $this->heureFermeture = $heureFermeture;
        return $this;
    }

    public function getNombreInscrits(): ?int
    {
        return $this->nombreInscrits;
    }

    public function setNombreInscrits(?int $nombreInscrits): self
    {
        $this->nombreInscrits = $nombreInscrits;
        return $this;
    }


    public function getSiegesPourvoir(): ?int
    {
        return $this->siegesPourvoir;
    }

    public function setSiegesPourvoir(?int $siegesPourvoir): self
    {
        $this->siegesPourvoir = $siegesPourvoir;
        return $this;
    }


    public function getNombreBureauxDeVote(): ?int
    {
        return $this->nombreBureauxDeVote;
    }

    public function setNombreBureauxDeVote(?int $nombreBureauxDeVote): self
    {
        $this->nombreBureauxDeVote = $nombreBureauxDeVote;
        return $this;
    }


    public function __toString(): string
    {
        return $this->nom . ' (' . $this->dateElection->format('d/m/Y') . ')';
    }
}

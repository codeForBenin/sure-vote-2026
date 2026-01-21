<?php

namespace App\Entity;

use App\Repository\CirconscriptionRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CirconscriptionRepository::class)]
#[ORM\Cache(usage: 'READ_ONLY')]
class Circonscription
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 10, unique: true)]
    private ?string $code = null;

    #[ORM\Column(nullable: true)]
    private array $villes = [];

    #[ORM\OneToMany(mappedBy: 'circonscription', targetEntity: CentreDeVote::class)]
    private Collection $centres;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $departement = null;

    #[ORM\Column(nullable: true)]
    private ?int $sieges = null;

    #[ORM\Column(nullable: true)]
    private ?int $population = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->centres = new ArrayCollection();
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
    public function getCode(): ?string
    {
        return $this->code;
    }
    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getVilles(): array
    {
        return $this->villes;
    }

    public function setVilles(?array $villes): self
    {
        $this->villes = $villes;
        return $this;
    }

    public function getCentres(): Collection
    {
        return $this->centres;
    }

    public function getDepartement(): ?string
    {
        return $this->departement;
    }

    public function setDepartement(?string $departement): self
    {
        $this->departement = $departement;

        return $this;
    }

    public function getSieges(): ?int
    {
        return $this->sieges;
    }

    public function setSieges(?int $sieges): self
    {
        $this->sieges = $sieges;

        return $this;
    }

    public function getPopulation(): ?int
    {
        return $this->population;
    }

    public function setPopulation(?int $population): self
    {
        $this->population = $population;

        return $this;
    }

    public function __toString(): string
    {
        return $this->nom . ' (' . $this->code . ')';
    }
}

<?php

namespace App\Entity;

use App\Repository\CentreDeVoteRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: CentreDeVoteRepository::class)]
class CentreDeVote
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 150)]
    private ?string $nom = null;

    #[ORM\Column(length: 50, unique: true)]
    private ?string $code = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $latitude = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $longitude = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $departement = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $commune = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $arrondissement = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $villageQuartier = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $searchContent = null;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isConfigValidated = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $nombreBureauxReels = null;

    #[ORM\ManyToOne(inversedBy: 'centres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Circonscription $circonscription = null;

    #[ORM\OneToMany(mappedBy: 'centre', targetEntity: BureauDeVote::class)]
    private Collection $bureaux;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->bureaux = new ArrayCollection();
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
    public function getLatitude(): ?float
    {
        return $this->latitude;
    }
    public function setLatitude(?float $latitude): self
    {
        $this->latitude = $latitude;
        return $this;
    }
    public function getLongitude(): ?float
    {
        return $this->longitude;
    }
    public function setLongitude(?float $longitude): self
    {
        $this->longitude = $longitude;
        return $this;
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

    public function getCommune(): ?string
    {
        return $this->commune;
    }

    public function setCommune(?string $commune): self
    {
        $this->commune = $commune;
        return $this;
    }

    public function getArrondissement(): ?string
    {
        return $this->arrondissement;
    }

    public function setArrondissement(?string $arrondissement): self
    {
        $this->arrondissement = $arrondissement;
        return $this;
    }

    public function getVillageQuartier(): ?string
    {
        return $this->villageQuartier;
    }

    public function setVillageQuartier(?string $villageQuartier): self
    {
        $this->villageQuartier = $villageQuartier;
        return $this;
    }

    public function getCirconscription(): ?Circonscription
    {
        return $this->circonscription;
    }
    public function setCirconscription(?Circonscription $circonscription): self
    {
        $this->circonscription = $circonscription;
        return $this;
    }
    public function getBureaux(): Collection
    {
        return $this->bureaux;
    }

    public function getSearchContent(): ?string
    {
        return $this->searchContent;
    }

    public function setSearchContent(?string $searchContent): self
    {
        $this->searchContent = $searchContent;
        return $this;
    }

    public function updateSearchContent(): void
    {
        $raw = implode(' ', [
            $this->getCode() ?? '',
            $this->getNom() ?? '',
            $this->getCommune() ?? '',
            $this->getArrondissement() ?? '',
            $this->getVillageQuartier() ?? '',
            $this->getDepartement() ?? '',
            $this->getCirconscription() ? $this->getCirconscription()->getNom() : ''
        ]);

        // 1. Translittération (Accents -> ASCII) & Minuscules
        $normalized = \transliterator_transliterate('Any-Latin; Latin-ASCII; Lower()', $raw);

        // 2. Remplacer les caractères non alphanumériques (tirets, guillemets, etc.) par des espaces
        $normalized = preg_replace('/[^a-z0-9]/', ' ', $normalized);

        // 3. Supprimer les espaces doubles
        $this->searchContent = trim(preg_replace('/\s+/', ' ', $normalized));
    }

    public function isConfigValidated(): bool
    {
        return $this->isConfigValidated;
    }

    public function setIsConfigValidated(bool $isConfigValidated): self
    {
        $this->isConfigValidated = $isConfigValidated;
        return $this;
    }

    public function getNombreBureauxReels(): ?int
    {
        return $this->nombreBureauxReels;
    }

    public function setNombreBureauxReels(?int $nombreBureauxReels): self
    {
        $this->nombreBureauxReels = $nombreBureauxReels;
        return $this;
    }

    public function __toString(): string
    {
        $nom = $this->nom ?? 'Centre sans nom';
        $arrondissementCommune = $this->arrondissement ? $this->arrondissement . ' - ' : '';
        $arrondissementCommune .= $this->commune ?? 'Commune inconnue';
        $departement = $this->departement ?? 'Departement inconnu';

        return sprintf('%s (%s) - %s', $nom, $arrondissementCommune, $departement);
    }
}

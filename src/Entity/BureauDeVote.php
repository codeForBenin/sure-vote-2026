<?php

namespace App\Entity;

use App\Repository\BureauDeVoteRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: BureauDeVoteRepository::class)]
class BureauDeVote
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private ?Uuid $id = null;

    #[ORM\Column(length: 100)]
    private ?string $nom = null;

    #[ORM\Column(length: 100, unique: true)]
    private ?string $code = null;

    #[ORM\Column]
    private ?int $nombreInscrits = null;

    #[ORM\ManyToOne(targetEntity: CentreDeVote::class, inversedBy: 'bureaux')]
    #[ORM\JoinColumn(nullable: false)]
    private ?CentreDeVote $centre = null;

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

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): self
    {
        $this->code = $code;
        return $this;
    }

    public function getNombreInscrits(): ?int
    {
        return $this->nombreInscrits;
    }

    public function setNombreInscrits(int $nombreInscrits): self
    {
        $this->nombreInscrits = $nombreInscrits;
        return $this;
    }

    public function getCentre(): ?CentreDeVote
    {
        return $this->centre;
    }

    public function setCentre(?CentreDeVote $centre): self
    {
        $this->centre = $centre;
        return $this;
    }

    public function __toString(): string
    {
        $nom = $this->nom ?? 'Bureau sans nom';
        $centreNom = $this->centre ? $this->centre->getNom() : 'Centre inconnu';
        $centreArrondissement = $this->centre ? $this->centre->getArrondissement() : 'Arrondissement inconnu';

        return sprintf('%s (%s) - %s', $nom, $centreNom, $centreArrondissement);
    }
}

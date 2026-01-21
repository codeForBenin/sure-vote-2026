<?php

namespace App\Entity;

use App\Repository\ParticipationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: ParticipationRepository::class)]
class Participation
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    private ?Uuid $id = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?BureauDeVote $bureauDeVote = null;

    #[ORM\Column]
    private ?int $nombreVotants = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $heurePointage = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $assesseur = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $metadataLocation = null;

    public function __construct()
    {
        $this->id = Uuid::v7();
        $this->createdAt = new \DateTimeImmutable();
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
    public function getNombreVotants(): ?int
    {
        return $this->nombreVotants;
    }
    public function setNombreVotants(int $nombreVotants): self
    {
        $this->nombreVotants = $nombreVotants;
        return $this;
    }
    public function getHeurePointage(): ?\DateTimeImmutable
    {
        return $this->heurePointage;
    }
    public function setHeurePointage(\DateTimeImmutable $heurePointage): self
    {
        $this->heurePointage = $heurePointage;
        return $this;
    }
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
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
    public function getMetadataLocation(): ?array
    {
        return $this->metadataLocation;
    }
    public function setMetadataLocation(?array $metadataLocation): self
    {
        $this->metadataLocation = $metadataLocation;
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->id . ' - ' . $this->bureauDeVote->getNom() . ' - ' . $this->assesseur->getNom() . ' - ' . $this->heurePointage->format('Y-m-d H:i:s') . ' - ' . $this->nombreVotants;
    }

}

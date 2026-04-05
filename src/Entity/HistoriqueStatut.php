<?php

namespace App\Entity;

use App\Repository\HistoriqueStatutRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueStatutRepository::class)]
class HistoriqueStatut
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Signalement::class, inversedBy: 'historique')]
    #[ORM\JoinColumn(nullable: false)]
    private Signalement $signalement;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $user;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $ancienStatut = null;

    #[ORM\Column(length: 20)]
    private string $nouveauStatut;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSignalement(): Signalement
    {
        return $this->signalement;
    }

    public function setSignalement(Signalement $signalement): static
    {
        $this->signalement = $signalement;
        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getAncienStatut(): ?string
    {
        return $this->ancienStatut;
    }

    public function setAncienStatut(?string $ancienStatut): static
    {
        $this->ancienStatut = $ancienStatut;
        return $this;
    }

    public function getNouveauStatut(): string
    {
        return $this->nouveauStatut;
    }

    public function setNouveauStatut(string $nouveauStatut): static
    {
        $this->nouveauStatut = $nouveauStatut;
        return $this;
    }

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getLabel(): string
    {
        $labels = Signalement::STATUT_LABELS;
        $ancien = $this->ancienStatut ? ($labels[$this->ancienStatut] ?? $this->ancienStatut) : 'Création';
        $nouveau = $labels[$this->nouveauStatut] ?? $this->nouveauStatut;
        return $ancien . ' → ' . $nouveau;
    }
}

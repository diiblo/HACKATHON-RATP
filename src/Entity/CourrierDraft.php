<?php

namespace App\Entity;

use App\Repository\CourrierDraftRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: CourrierDraftRepository::class)]
#[ORM\HasLifecycleCallbacks]
class CourrierDraft
{
    const STATUTS = ['brouillon', 'en_validation', 'valide', 'refuse'];
    const DISPATCH_STATUSES = ['cree', 'envoye', 'distribue', 'receptionne'];

    const STATUT_LABELS = [
        'brouillon'    => 'Brouillon',
        'en_validation'=> 'En validation',
        'valide'       => 'Validé',
        'refuse'       => 'Refusé',
    ];

    const STATUT_BADGES = [
        'brouillon'    => 'bg-secondary',
        'en_validation'=> 'bg-warning text-dark',
        'valide'       => 'bg-success',
        'refuse'       => 'bg-danger',
    ];

    const DISPATCH_LABELS = [
        'cree' => 'Créé chez Maileva',
        'envoye' => 'Envoyé',
        'distribue' => 'Distribué / mis à disposition',
        'receptionne' => 'Réceptionné',
    ];

    const DISPATCH_BADGES = [
        'cree' => 'bg-secondary',
        'envoye' => 'bg-info text-dark',
        'distribue' => 'bg-primary',
        'receptionne' => 'bg-success',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Signalement::class, inversedBy: 'courriers')]
    #[ORM\JoinColumn(nullable: false)]
    private Signalement $signalement;

    #[ORM\Column(type: 'text')]
    private string $contenu;

    #[ORM\Column(length: 20)]
    private string $statut = 'brouillon';

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $validatedBy = null;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $dispatchStatus = null;

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $dispatchReference = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $dispatchedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $lastDispatchUpdateAt = null;

    #[ORM\Column(type: 'json')]
    private array $dispatchJournal = [];

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new \DateTime();
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

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getStatut(): string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getValidatedBy(): ?User
    {
        return $this->validatedBy;
    }

    public function setValidatedBy(?User $validatedBy): static
    {
        $this->validatedBy = $validatedBy;
        return $this;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function getDispatchStatus(): ?string
    {
        return $this->dispatchStatus;
    }

    public function setDispatchStatus(?string $dispatchStatus): static
    {
        $this->dispatchStatus = $dispatchStatus;
        return $this;
    }

    public function getDispatchReference(): ?string
    {
        return $this->dispatchReference;
    }

    public function setDispatchReference(?string $dispatchReference): static
    {
        $this->dispatchReference = $dispatchReference;
        return $this;
    }

    public function getDispatchedAt(): ?\DateTime
    {
        return $this->dispatchedAt;
    }

    public function setDispatchedAt(?\DateTime $dispatchedAt): static
    {
        $this->dispatchedAt = $dispatchedAt;
        return $this;
    }

    public function getLastDispatchUpdateAt(): ?\DateTime
    {
        return $this->lastDispatchUpdateAt;
    }

    public function setLastDispatchUpdateAt(?\DateTime $lastDispatchUpdateAt): static
    {
        $this->lastDispatchUpdateAt = $lastDispatchUpdateAt;
        return $this;
    }

    public function getDispatchJournal(): array
    {
        return $this->dispatchJournal;
    }

    public function setDispatchJournal(array $dispatchJournal): static
    {
        $this->dispatchJournal = $dispatchJournal;
        return $this;
    }

    public function addDispatchJournalEntry(string $status, string $message, ?\DateTimeImmutable $at = null): static
    {
        $timestamp = $at ?? new \DateTimeImmutable();
        $journal = $this->dispatchJournal;
        $journal[] = [
            'status' => $status,
            'label' => self::DISPATCH_LABELS[$status] ?? $status,
            'message' => $message,
            'at' => $timestamp->format(\DateTimeInterface::ATOM),
        ];
        $this->dispatchJournal = $journal;
        $this->lastDispatchUpdateAt = \DateTime::createFromImmutable($timestamp);

        return $this;
    }

    public function getStatutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public function getStatutBadge(): string
    {
        return self::STATUT_BADGES[$this->statut] ?? 'bg-secondary';
    }

    public function isEditable(): bool
    {
        return in_array($this->statut, ['brouillon', 'refuse']);
    }

    public function getDispatchStatusLabel(): ?string
    {
        if ($this->dispatchStatus === null) {
            return null;
        }

        return self::DISPATCH_LABELS[$this->dispatchStatus] ?? $this->dispatchStatus;
    }

    public function getDispatchStatusBadge(): string
    {
        if ($this->dispatchStatus === null) {
            return 'bg-secondary';
        }

        return self::DISPATCH_BADGES[$this->dispatchStatus] ?? 'bg-secondary';
    }

    public function isDispatched(): bool
    {
        return $this->dispatchStatus !== null;
    }

    public function isDispatchComplete(): bool
    {
        return $this->dispatchStatus === 'receptionne';
    }
}

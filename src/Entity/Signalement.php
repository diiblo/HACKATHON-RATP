<?php

namespace App\Entity;

use App\Repository\SignalementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SignalementRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Signalement
{
    const STATUTS = ['nouveau', 'qualification', 'validation', 'traite', 'archive'];
    const TYPES = ['incident', 'positif'];
    const CANAUX = ['formulaire', 'email', 'terrain', 'social', 'autre'];
    const GRAVITES = ['faible', 'moyen', 'grave'];

    const STATUT_LABELS = [
        'nouveau'       => 'Nouveau',
        'qualification' => 'Qualification',
        'validation'    => 'Validation',
        'traite'        => 'Traité',
        'archive'       => 'Archivé',
    ];

    const STATUT_BADGES = [
        'nouveau'       => 'bg-secondary',
        'qualification' => 'bg-info text-dark',
        'validation'    => 'bg-warning text-dark',
        'traite'        => 'bg-success',
        'archive'       => 'bg-dark',
    ];

    const GRAVITE_BADGES = [
        'faible' => 'bg-success',
        'moyen'  => 'bg-warning text-dark',
        'grave'  => 'bg-danger',
    ];

    const CANAL_LABELS = [
        'formulaire' => 'Formulaire web',
        'terrain'    => 'Terrain (QR)',
        'social'     => 'Réseaux sociaux',
        'dm'         => 'Message direct',
        'email'      => 'E-mail',
        'autre'      => 'Autre',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Agent::class, inversedBy: 'signalements')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Agent $agent = null;

    /** Décrit l'agent pour les signalements publics (pas de FK agent disponible) */
    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $agentDescription = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    private User $createdBy;

    #[ORM\Column(length: 20)]
    private string $type;

    #[ORM\Column(length: 20)]
    private string $canal;

    #[ORM\Column(length: 255)]
    private string $titre;

    #[ORM\Column(type: 'text')]
    private string $description;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $sourceLine = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $sourceVehicle = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $sourceStop = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $sourceEntryMode = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $sourcePlatform = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $sourceLanguage = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $voiceTranscript = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $translatedDescription = null;

    #[ORM\Column]
    private \DateTimeImmutable $dateFait;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $gravite = null;

    #[ORM\Column(length: 20)]
    private string $statut = 'nouveau';

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $plainteDeposeeAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $plainteCommentaire = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $plainantNom = null;

    #[ORM\Column(length: 150, nullable: true)]
    private ?string $plainantEmail = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $plainantTelephone = null;

    #[ORM\OneToMany(targetEntity: CommentaireSignalement::class, mappedBy: 'signalement', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $commentaires;

    #[ORM\OneToMany(targetEntity: HistoriqueStatut::class, mappedBy: 'signalement', cascade: ['remove'])]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $historique;

    #[ORM\OneToMany(targetEntity: CourrierDraft::class, mappedBy: 'signalement', cascade: ['remove'])]
    private Collection $courriers;

    #[ORM\OneToMany(targetEntity: PieceJointe::class, mappedBy: 'signalement', cascade: ['remove'])]
    #[ORM\OrderBy(['uploadedAt' => 'ASC'])]
    private Collection $piecesJointes;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->commentaires = new ArrayCollection();
        $this->historique = new ArrayCollection();
        $this->courriers = new ArrayCollection();
        $this->piecesJointes = new ArrayCollection();
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

    public function getAgent(): ?Agent
    {
        return $this->agent;
    }

    public function setAgent(?Agent $agent): static
    {
        $this->agent = $agent;
        return $this;
    }

    public function getAgentDescription(): ?string
    {
        return $this->agentDescription;
    }

    public function setAgentDescription(?string $agentDescription): static
    {
        $this->agentDescription = $agentDescription;
        return $this;
    }

    /** Retourne le nom à afficher (agent FK ou description texte) */
    public function getAgentDisplayName(): string
    {
        if ($this->agent !== null) {
            return $this->agent->getFullName();
        }
        return $this->agentDescription ?? 'À identifier';
    }

    public function getCreatedBy(): User
    {
        return $this->createdBy;
    }

    public function setCreatedBy(User $createdBy): static
    {
        $this->createdBy = $createdBy;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCanal(): string
    {
        return $this->canal;
    }

    public function setCanal(string $canal): static
    {
        $this->canal = $canal;
        return $this;
    }

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getSourceLine(): ?string
    {
        return $this->sourceLine;
    }

    public function setSourceLine(?string $sourceLine): static
    {
        $this->sourceLine = $sourceLine;
        return $this;
    }

    public function getSourceVehicle(): ?string
    {
        return $this->sourceVehicle;
    }

    public function setSourceVehicle(?string $sourceVehicle): static
    {
        $this->sourceVehicle = $sourceVehicle;
        return $this;
    }

    public function getSourceStop(): ?string
    {
        return $this->sourceStop;
    }

    public function setSourceStop(?string $sourceStop): static
    {
        $this->sourceStop = $sourceStop;
        return $this;
    }

    public function getSourceEntryMode(): ?string
    {
        return $this->sourceEntryMode;
    }

    public function setSourceEntryMode(?string $sourceEntryMode): static
    {
        $this->sourceEntryMode = $sourceEntryMode;
        return $this;
    }

    public function getSourcePlatform(): ?string
    {
        return $this->sourcePlatform;
    }

    public function setSourcePlatform(?string $sourcePlatform): static
    {
        $this->sourcePlatform = $sourcePlatform;
        return $this;
    }

    public function getSourceLanguage(): ?string
    {
        return $this->sourceLanguage;
    }

    public function setSourceLanguage(?string $sourceLanguage): static
    {
        $this->sourceLanguage = $sourceLanguage;
        return $this;
    }

    public function getVoiceTranscript(): ?string
    {
        return $this->voiceTranscript;
    }

    public function setVoiceTranscript(?string $voiceTranscript): static
    {
        $this->voiceTranscript = $voiceTranscript;
        return $this;
    }

    public function getTranslatedDescription(): ?string
    {
        return $this->translatedDescription;
    }

    public function setTranslatedDescription(?string $translatedDescription): static
    {
        $this->translatedDescription = $translatedDescription;
        return $this;
    }

    public function getDateFait(): \DateTimeImmutable
    {
        return $this->dateFait;
    }

    public function setDateFait(\DateTimeImmutable $dateFait): static
    {
        $this->dateFait = $dateFait;
        return $this;
    }

    public function getGravite(): ?string
    {
        return $this->gravite;
    }

    public function setGravite(?string $gravite): static
    {
        $this->gravite = $gravite;
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

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function getPlainteDeposeeAt(): ?\DateTime
    {
        return $this->plainteDeposeeAt;
    }

    public function setPlainteDeposeeAt(?\DateTime $plainteDeposeeAt): static
    {
        $this->plainteDeposeeAt = $plainteDeposeeAt;
        return $this;
    }

    public function getPlainteCommentaire(): ?string
    {
        return $this->plainteCommentaire;
    }

    public function setPlainteCommentaire(?string $plainteCommentaire): static
    {
        $this->plainteCommentaire = $plainteCommentaire;
        return $this;
    }

    public function getPlainantNom(): ?string { return $this->plainantNom; }
    public function setPlainantNom(?string $plainantNom): static { $this->plainantNom = $plainantNom; return $this; }

    public function getPlainantEmail(): ?string { return $this->plainantEmail; }
    public function setPlainantEmail(?string $plainantEmail): static { $this->plainantEmail = $plainantEmail; return $this; }

    public function getPlainantTelephone(): ?string { return $this->plainantTelephone; }
    public function setPlainantTelephone(?string $plainantTelephone): static { $this->plainantTelephone = $plainantTelephone; return $this; }

    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function getHistorique(): Collection
    {
        return $this->historique;
    }

    public function getCourriers(): Collection
    {
        return $this->courriers;
    }

    public function getPiecesJointes(): Collection
    {
        return $this->piecesJointes;
    }

    public function getStatutLabel(): string
    {
        return self::STATUT_LABELS[$this->statut] ?? $this->statut;
    }

    public function getStatutBadge(): string
    {
        return self::STATUT_BADGES[$this->statut] ?? 'bg-secondary';
    }

    public function getGraviteBadge(): string
    {
        return self::GRAVITE_BADGES[$this->gravite] ?? 'bg-secondary';
    }

    public function getCanalLabel(): string
    {
        return self::CANAL_LABELS[$this->canal] ?? $this->canal;
    }

    public function isIncident(): bool
    {
        return $this->type === 'incident';
    }

    public function getLatestCourrier(): ?CourrierDraft
    {
        if ($this->courriers->isEmpty()) {
            return null;
        }
        return $this->courriers->last();
    }

    public function getSourceContextLabel(): ?string
    {
        $parts = [];
        if ($this->sourceLine) {
            $parts[] = 'Ligne ' . $this->sourceLine;
        }
        if ($this->sourceVehicle) {
            $parts[] = 'Véhicule ' . $this->sourceVehicle;
        }
        if ($this->sourceStop) {
            $parts[] = 'Arrêt ' . $this->sourceStop;
        }

        return $parts === [] ? null : implode(' · ', $parts);
    }

    public function getVideoDeadlineAt(): \DateTimeImmutable
    {
        return $this->getCreatedAt()->modify('+24 hours');
    }

    public function getVideoHoursRemaining(): int
    {
        $remaining = $this->getVideoDeadlineAt()->getTimestamp() - time();
        return (int) ceil($remaining / 3600);
    }

    public function isVideoEvidenceExpired(): bool
    {
        return $this->getVideoDeadlineAt() <= new \DateTimeImmutable();
    }

    public function isVideoEvidenceUrgent(): bool
    {
        return !$this->isVideoEvidenceExpired() && $this->getVideoHoursRemaining() <= 6;
    }

    public function hasComplaintProof(): bool
    {
        return $this->plainteDeposeeAt !== null;
    }
}

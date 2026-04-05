<?php

namespace App\Entity;

use App\Repository\PieceJointeRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PieceJointeRepository::class)]
class PieceJointe
{
    public const VISIBILITY_PUBLIC = 'public';
    public const VISIBILITY_INTERNAL = 'internal';
    public const VISIBILITY_RESTRICTED = 'restricted';

    const EXTENSIONS_AUTORISEES = ['docx', 'doc', 'pdf', 'jpg', 'jpeg', 'png', 'mp3', 'wav', 'm4a'];
    const MIME_AUTORISEES = [
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/msword',
        'application/pdf',
        'image/jpeg',
        'image/png',
        'audio/mpeg',
        'audio/wav',
        'audio/x-wav',
        'audio/mp4',
        'audio/x-m4a',
    ];
    const TAILLE_MAX = 10 * 1024 * 1024; // 10 Mo

    const ICONES = [
        'docx' => 'bi-file-earmark-word text-primary',
        'doc'  => 'bi-file-earmark-word text-primary',
        'pdf'  => 'bi-file-earmark-pdf text-danger',
        'jpg'  => 'bi-file-earmark-image text-success',
        'jpeg' => 'bi-file-earmark-image text-success',
        'png'  => 'bi-file-earmark-image text-success',
        'mp3'  => 'bi-file-earmark-music text-info',
        'wav'  => 'bi-file-earmark-music text-info',
        'm4a'  => 'bi-file-earmark-music text-info',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Signalement::class, inversedBy: 'piecesJointes')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Signalement $signalement;

    /** Nom de fichier sur le disque (UUID + extension) */
    #[ORM\Column(length: 255)]
    private string $filename;

    /** Nom original du fichier uploadé */
    #[ORM\Column(length: 255)]
    private string $originalName;

    #[ORM\Column(length: 100)]
    private string $mimeType;

    #[ORM\Column]
    private int $size;

    #[ORM\Column]
    private \DateTimeImmutable $uploadedAt;

    #[ORM\Column(length: 20)]
    private string $visibility = self::VISIBILITY_INTERNAL;

    #[ORM\Column(length: 30)]
    private string $category = 'document';

    /** Null si uploadé depuis un formulaire public */
    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $uploadedBy = null;

    public function __construct()
    {
        $this->uploadedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }

    public function getSignalement(): Signalement { return $this->signalement; }
    public function setSignalement(Signalement $s): static { $this->signalement = $s; return $this; }

    public function getFilename(): string { return $this->filename; }
    public function setFilename(string $f): static { $this->filename = $f; return $this; }

    public function getOriginalName(): string { return $this->originalName; }
    public function setOriginalName(string $n): static { $this->originalName = $n; return $this; }

    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $m): static { $this->mimeType = $m; return $this; }

    public function getSize(): int { return $this->size; }
    public function setSize(int $s): static { $this->size = $s; return $this; }

    public function getUploadedAt(): \DateTimeImmutable { return $this->uploadedAt; }

    public function getVisibility(): string { return $this->visibility; }
    public function setVisibility(string $visibility): static { $this->visibility = $visibility; return $this; }

    public function getCategory(): string { return $this->category; }
    public function setCategory(string $category): static { $this->category = $category; return $this; }

    public function getUploadedBy(): ?User { return $this->uploadedBy; }
    public function setUploadedBy(?User $u): static { $this->uploadedBy = $u; return $this; }

    public function getExtension(): string
    {
        return strtolower(pathinfo($this->originalName, PATHINFO_EXTENSION));
    }

    public function getIconClass(): string
    {
        return self::ICONES[$this->getExtension()] ?? 'bi-file-earmark text-muted';
    }

    public function getSizeFormatted(): string
    {
        if ($this->size < 1024) return $this->size . ' o';
        if ($this->size < 1024 * 1024) return round($this->size / 1024, 1) . ' Ko';
        return round($this->size / (1024 * 1024), 1) . ' Mo';
    }

    public function isPubliclyAccessible(): bool
    {
        return $this->visibility === self::VISIBILITY_PUBLIC;
    }

    public function getVisibilityLabel(): string
    {
        return match ($this->visibility) {
            self::VISIBILITY_PUBLIC => 'Public',
            self::VISIBILITY_RESTRICTED => 'Restreint',
            default => 'Interne',
        };
    }
}

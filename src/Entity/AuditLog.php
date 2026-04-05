<?php

namespace App\Entity;

use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 80)]
    private string $action;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $actorEmail = null;

    #[ORM\Column(length: 120)]
    private string $actorLabel = 'system';

    #[ORM\Column(length: 80, nullable: true)]
    private ?string $subjectType = null;

    #[ORM\Column(nullable: true)]
    private ?int $subjectId = null;

    #[ORM\Column(type: 'text')]
    private string $summary;

    #[ORM\Column(type: 'json')]
    private array $metadata = [];

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getAction(): string { return $this->action; }
    public function setAction(string $action): static { $this->action = $action; return $this; }
    public function getActorEmail(): ?string { return $this->actorEmail; }
    public function setActorEmail(?string $actorEmail): static { $this->actorEmail = $actorEmail; return $this; }
    public function getActorLabel(): string { return $this->actorLabel; }
    public function setActorLabel(string $actorLabel): static { $this->actorLabel = $actorLabel; return $this; }
    public function getSubjectType(): ?string { return $this->subjectType; }
    public function setSubjectType(?string $subjectType): static { $this->subjectType = $subjectType; return $this; }
    public function getSubjectId(): ?int { return $this->subjectId; }
    public function setSubjectId(?int $subjectId): static { $this->subjectId = $subjectId; return $this; }
    public function getSummary(): string { return $this->summary; }
    public function setSummary(string $summary): static { $this->summary = $summary; return $this; }
    public function getMetadata(): array { return $this->metadata; }
    public function setMetadata(array $metadata): static { $this->metadata = $metadata; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}

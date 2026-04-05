<?php

namespace App\Entity;

use App\Repository\AiProviderConfigRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: AiProviderConfigRepository::class)]
#[ORM\HasLifecycleCallbacks]
class AiProviderConfig
{
    public const PROVIDER_TYPES = [
        'openai_compatible' => 'API OpenAI-compatible',
        'openrouter' => 'OpenRouter',
        'gemini' => 'Google Gemini',
        'ollama' => 'Ollama (modèles open source locaux)',
        'ollama_cloud' => 'Ollama Cloud',
    ];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 120)]
    private string $name;

    #[ORM\Column(length: 40)]
    private string $providerType = 'openai_compatible';

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $vendorLabel = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiBaseUrl = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $apiPath = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $apiKeyEncrypted = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $extraHeaders = null;

    #[ORM\Column(length: 160)]
    private string $model;

    #[ORM\Column(type: 'float')]
    private float $temperature = 0.2;

    #[ORM\Column]
    private int $timeoutSeconds = 20;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $systemPrompt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $contextTemplate = null;

    #[ORM\Column]
    private bool $active = false;

    #[ORM\Column]
    private bool $isDefault = false;

    #[ORM\Column]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTime $updatedAt = null;

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

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getProviderType(): string
    {
        return $this->providerType;
    }

    public function setProviderType(string $providerType): static
    {
        $this->providerType = $providerType;
        return $this;
    }

    public function getVendorLabel(): ?string
    {
        return $this->vendorLabel;
    }

    public function setVendorLabel(?string $vendorLabel): static
    {
        $this->vendorLabel = $vendorLabel;
        return $this;
    }

    public function getApiBaseUrl(): ?string
    {
        return $this->apiBaseUrl;
    }

    public function setApiBaseUrl(?string $apiBaseUrl): static
    {
        $this->apiBaseUrl = $apiBaseUrl;
        return $this;
    }

    public function getApiPath(): ?string
    {
        return $this->apiPath;
    }

    public function setApiPath(?string $apiPath): static
    {
        $this->apiPath = $apiPath;
        return $this;
    }

    public function getApiKeyEncrypted(): ?string
    {
        return $this->apiKeyEncrypted;
    }

    public function setApiKeyEncrypted(?string $apiKeyEncrypted): static
    {
        $this->apiKeyEncrypted = $apiKeyEncrypted;
        return $this;
    }

    public function getExtraHeaders(): ?string
    {
        return $this->extraHeaders;
    }

    public function setExtraHeaders(?string $extraHeaders): static
    {
        $this->extraHeaders = $extraHeaders;
        return $this;
    }

    public function getModel(): string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getTemperature(): float
    {
        return $this->temperature;
    }

    public function setTemperature(float $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getTimeoutSeconds(): int
    {
        return $this->timeoutSeconds;
    }

    public function setTimeoutSeconds(int $timeoutSeconds): static
    {
        $this->timeoutSeconds = $timeoutSeconds;
        return $this;
    }

    public function getSystemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function setSystemPrompt(?string $systemPrompt): static
    {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    public function getContextTemplate(): ?string
    {
        return $this->contextTemplate;
    }

    public function setContextTemplate(?string $contextTemplate): static
    {
        $this->contextTemplate = $contextTemplate;
        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;
        return $this;
    }

    public function isDefault(): bool
    {
        return $this->isDefault;
    }

    public function setIsDefault(bool $isDefault): static
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getDisplayLabel(): string
    {
        return trim(($this->vendorLabel ?: $this->name) . ' · ' . $this->model);
    }

    public function hasApiKey(): bool
    {
        return $this->apiKeyEncrypted !== null && $this->apiKeyEncrypted !== '';
    }

    public function getResolvedApiPath(): string
    {
        if ($this->apiPath !== null && trim($this->apiPath) !== '') {
            return trim($this->apiPath);
        }

        return match ($this->providerType) {
            'openrouter' => '/api/v1/chat/completions',
            'gemini' => '/v1beta/models/{model}:generateContent',
            'ollama', 'ollama_cloud' => '/api/chat',
            default => '/chat/completions',
        };
    }
}

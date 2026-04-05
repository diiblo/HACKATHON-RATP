<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use App\Entity\User;
use App\Repository\AiProviderConfigRepository;
use Doctrine\ORM\EntityManagerInterface;

class AiConfigurationManager
{
    public function __construct(
        private readonly AiProviderConfigRepository $repository,
        private readonly EntityManagerInterface $em,
        private readonly AiSecretManager $secretManager,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function save(AiProviderConfig $config, ?string $plainApiKey, ?User $actor = null): void
    {
        $isNew = $config->getId() === null;
        if ($config->isDefault()) {
            foreach ($this->repository->findAll() as $existing) {
                if ($existing->getId() !== $config->getId()) {
                    $existing->setIsDefault(false);
                }
            }
        }

        if ($plainApiKey !== null && $plainApiKey !== '') {
            $config->setApiKeyEncrypted($this->secretManager->encrypt($plainApiKey));
        }

        $this->em->persist($config);
        $this->auditLogger->log(
            $isNew ? 'ai.config.created' : 'ai.config.updated',
            sprintf('Configuration IA %s (%s).', $config->getName(), $isNew ? 'créée' : 'mise à jour'),
            [
                'providerType' => $config->getProviderType(),
                'model' => $config->getModel(),
                'active' => $config->isActive(),
                'default' => $config->isDefault(),
            ],
            $config,
            $actor
        );
        $this->em->flush();
    }

    public function getDefaultActiveConfig(): ?AiProviderConfig
    {
        return $this->repository->findDefaultActive();
    }

    /**
     * @return AiProviderConfig[]
     */
    public function getActiveFailoverChain(): array
    {
        return $this->repository->findActiveFailoverChain();
    }

    public function maskApiKey(AiProviderConfig $config): ?string
    {
        $plain = $this->secretManager->decrypt($config->getApiKeyEncrypted());
        if ($plain === null || strlen($plain) < 8) {
            return $plain;
        }

        return substr($plain, 0, 4) . str_repeat('*', max(0, strlen($plain) - 8)) . substr($plain, -4);
    }
}

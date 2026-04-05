<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use App\Entity\User;

class AiFailoverRouter
{
    public function __construct(
        private readonly AiConfigurationManager $configurationManager,
        private readonly AiGateway $gateway,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function chat(array $messages, ?object $subject = null, ?User $actor = null): array
    {
        $chain = $this->configurationManager->getActiveFailoverChain();
        if ($chain === []) {
            throw new \RuntimeException('Aucune configuration IA active n\'est disponible.');
        }

        $attempts = [];
        $lastException = null;

        foreach ($chain as $index => $config) {
            try {
                $raw = $this->gateway->chat($config, $messages);
                $attempts[] = [
                    'provider' => $config->getDisplayLabel(),
                    'name' => $config->getName(),
                    'status' => 'success',
                    'fallback' => $index > 0,
                ];

                if ($index > 0) {
                    $this->auditLogger->log(
                        'ai.failover.used',
                        sprintf('Bascule automatique IA vers %s.', $config->getName()),
                        ['attempts' => $attempts],
                        $subject,
                        $actor
                    );
                }

                return [
                    'config' => $config,
                    'raw' => $raw,
                    'attempts' => $attempts,
                    'usedFallback' => $index > 0,
                ];
            } catch (\Throwable $e) {
                $attempts[] = [
                    'provider' => $config->getDisplayLabel(),
                    'name' => $config->getName(),
                    'status' => 'failed',
                    'error' => $e->getMessage(),
                    'retryable' => $this->isRetryable($e),
                ];
                $lastException = $e;

                if (!$this->isRetryable($e) || $index === array_key_last($chain)) {
                    break;
                }
            }
        }

        $this->auditLogger->log(
            'ai.failover.failed',
            'La chaîne de secours IA a échoué.',
            ['attempts' => $attempts],
            $subject,
            $actor
        );

        if ($lastException !== null) {
            throw $lastException;
        }

        throw new \RuntimeException('Échec de la chaîne IA.');
    }

    private function isRetryable(\Throwable $e): bool
    {
        $message = mb_strtolower($e->getMessage());

        foreach ([
            'timeout',
            'idle timeout',
            'rate limit',
            'quota',
            '429',
            'temporarily unavailable',
            'connection refused',
            'could not resolve',
            '502',
            '503',
            '504',
            'network',
            'transport',
        ] as $needle) {
            if (str_contains($message, $needle)) {
                return true;
            }
        }

        return false;
    }
}

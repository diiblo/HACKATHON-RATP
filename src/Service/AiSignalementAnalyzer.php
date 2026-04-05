<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use App\Entity\Signalement;
use App\Entity\User;

class AiSignalementAnalyzer
{
    public function __construct(
        private readonly AiConfigurationManager $configurationManager,
        private readonly AiPromptBuilder $promptBuilder,
        private readonly AiFailoverRouter $failoverRouter,
    ) {}

    public function analyze(Signalement $signalement, ?User $actor = null): array
    {
        $chain = $this->configurationManager->getActiveFailoverChain();
        if ($chain === []) {
            throw new \RuntimeException('Aucune configuration IA active n\'est disponible.');
        }

        /** @var AiProviderConfig $promptConfig */
        $promptConfig = $chain[0];
        $messages = $this->promptBuilder->buildSignalementAnalysisMessages($promptConfig, $signalement);
        $result = $this->failoverRouter->chat($messages, $signalement, $actor);
        /** @var AiProviderConfig $config */
        $config = $result['config'];
        $raw = $result['raw'];
        $parsed = $this->parseJson($raw);

        return [
            'provider' => $config->getDisplayLabel(),
            'providerName' => $config->getName(),
            'usedFallback' => $result['usedFallback'],
            'attempts' => $result['attempts'],
            'summary' => $parsed['summary'] ?? $raw,
            'qualification' => $parsed['qualification'] ?? 'Non fournie',
            'riskFactors' => $this->normalizeList($parsed['risk_factors'] ?? []),
            'recommendedActions' => $this->normalizeList($parsed['recommended_actions'] ?? []),
            'courrierGuidance' => $parsed['courrier_guidance'] ?? 'Non fournie',
            'recommendedDecision' => $parsed['recommended_decision'] ?? 'Non fournie',
            'urgencyLevel' => $parsed['urgency_level'] ?? 'Non fournie',
            'recommendedStatus' => $parsed['recommended_status'] ?? 'Non fourni',
            'decisionScore' => $this->normalizeScore($parsed['decision_score'] ?? null),
            'videoPreservationAction' => $parsed['video_preservation_action'] ?? 'Non fournie',
            'alertEmailSubject' => $parsed['alert_email_subject'] ?? 'Alerte décisionnelle signalement',
            'alertEmailBody' => $parsed['alert_email_body'] ?? 'Aucun contenu d’alerte généré.',
            'alertTargetRoles' => $this->normalizeRoles($parsed['alert_target_roles'] ?? []),
            'courrierDraft' => $parsed['courrier_draft'] ?? '',
            'raw' => $raw,
        ];
    }

    private function parseJson(string $raw): array
    {
        $trimmed = trim($raw);
        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*|\s*```$/', '', $trimmed) ?: $trimmed;
        }

        $decoded = json_decode($trimmed, true);
        return is_array($decoded) ? $decoded : [];
    }

    private function normalizeList(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(static fn($item) => trim((string) $item), $value), static fn($item) => $item !== ''));
        }

        $text = trim((string) $value);
        if ($text === '') {
            return [];
        }

        return [$text];
    }

    private function normalizeRoles(mixed $value): array
    {
        $roles = $this->normalizeList($value);
        if ($roles === []) {
            return ['ROLE_MANAGER', 'ROLE_RH'];
        }

        return array_values(array_unique(array_map(static function (string $role): string {
            $role = strtoupper(trim($role));
            return str_starts_with($role, 'ROLE_') ? $role : 'ROLE_' . $role;
        }, $roles)));
    }

    private function normalizeScore(mixed $value): int
    {
        $score = (int) $value;
        if ($score < 1) {
            return 1;
        }
        if ($score > 4) {
            return 4;
        }

        return $score;
    }
}

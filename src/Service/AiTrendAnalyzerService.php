<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\SignalementRepository;

class AiTrendAnalyzerService
{
    public function __construct(
        private readonly AiFailoverRouter $failoverRouter,
        private readonly AiConfigurationManager $configurationManager,
        private readonly SignalementRepository $signalementRepository,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function analyze(?User $actor = null): array
    {
        $chain = $this->configurationManager->getActiveFailoverChain();
        if ($chain === []) {
            throw new \RuntimeException('Aucune configuration IA active disponible.');
        }

        $stats = $this->signalementRepository->findTrendStats();
        $messages = $this->buildMessages($stats);
        $result = $this->failoverRouter->chat($messages, null, $actor);
        $parsed = $this->parseJson($result['raw']);

        $this->auditLogger->log(
            'ai.trend.analysis',
            'Analyse des tendances IA effectuée.',
            ['provider' => $result['config']->getDisplayLabel(), 'usedFallback' => $result['usedFallback']],
            null,
            $actor
        );

        return [
            'provider'        => $result['config']->getDisplayLabel(),
            'usedFallback'    => $result['usedFallback'],
            'resume'          => $parsed['resume_executif'] ?? 'Analyse non disponible.',
            'tendances'       => $parsed['tendances'] ?? '',
            'risques'         => $this->normalizeList($parsed['risques'] ?? []),
            'recommandations' => $this->normalizeList($parsed['recommandations'] ?? []),
            'niveauAlerte'    => $parsed['niveau_alerte_global'] ?? 'normal',
            'raw'             => $result['raw'],
        ];
    }

    private function buildMessages(array $stats): array
    {
        $systemPrompt = <<<PROMPT
Tu es un expert en analyse RH et sécurité pour la RATP.
Analyse les statistiques de signalements et détecte les tendances significatives.
Réponds UNIQUEMENT en JSON valide avec les clés :
resume_executif, tendances, risques (liste), recommandations (liste), niveau_alerte_global (normal|attention|critique).
PROMPT;

        $currentMap  = $this->buildStatMap($stats['current']);
        $previousMap = $this->buildStatMap($stats['previous']);

        $totalCurrentIncidents  = array_sum(array_filter($currentMap, fn($k) => str_contains($k, 'incident'), ARRAY_FILTER_USE_KEY));
        $totalPreviousIncidents = array_sum(array_filter($previousMap, fn($k) => str_contains($k, 'incident'), ARRAY_FILTER_USE_KEY));

        $totalCurrentPositifs  = $currentMap['positif_'] ?? 0;
        $totalPreviousPositifs = $previousMap['positif_'] ?? 0;

        $lines = [];
        $lines[] = 'DONNÉES RATP — Analyse des 30 derniers jours (vs 30 jours précédents)';
        $lines[] = '';
        $lines[] = sprintf(
            'INCIDENTS — Période courante : %d (vs %d période précédente%s)',
            $totalCurrentIncidents,
            $totalPreviousIncidents,
            $this->delta($totalCurrentIncidents, $totalPreviousIncidents)
        );

        foreach (['faible', 'moyen', 'grave'] as $gravite) {
            $cur  = $currentMap["incident_{$gravite}"] ?? 0;
            $prev = $previousMap["incident_{$gravite}"] ?? 0;
            $lines[] = sprintf('  - %s : %d (vs %d%s)', ucfirst($gravite), $cur, $prev, $this->delta($cur, $prev));
        }

        $lines[] = '';
        $lines[] = sprintf(
            'AVIS POSITIFS : %d (vs %d%s)',
            $totalCurrentPositifs,
            $totalPreviousPositifs,
            $this->delta($totalCurrentPositifs, $totalPreviousPositifs)
        );

        if (!empty($stats['canaux'])) {
            $lines[] = '';
            $lines[] = 'CANAUX DE SAISIE (30 jours) :';
            foreach ($stats['canaux'] as $row) {
                $lines[] = sprintf('  - %s : %d signalement(s)', $row['canal'], (int) $row['nb']);
            }
        }

        if (!empty($stats['topAgents'])) {
            $lines[] = '';
            $lines[] = 'AGENTS LES PLUS IMPLIQUÉS (incidents, 30 jours) :';
            foreach ($stats['topAgents'] as $row) {
                $centre = $row['centre'] ? " — {$row['centre']}" : '';
                $lines[] = sprintf('  - %s %s%s : %d incident(s)', $row['prenom'], $row['nom'], $centre, (int) $row['nb']);
            }
        }

        $lines[] = '';
        $lines[] = 'Identifie les tendances, les risques et recommande des actions prioritaires pour la direction RATP.';

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => implode("\n", $lines)],
        ];
    }

    private function buildStatMap(array $rows): array
    {
        $map = [];
        foreach ($rows as $row) {
            $key = $row['type'] . '_' . ($row['gravite'] ?? '');
            $map[$key] = ($map[$key] ?? 0) + (int) $row['nb'];
        }
        return $map;
    }

    private function delta(int $current, int $previous): string
    {
        if ($previous === 0) {
            return $current > 0 ? ' : nouveau' : '';
        }
        $pct = (int) round(($current - $previous) / $previous * 100);
        if ($pct === 0) {
            return ' : stable';
        }
        return $pct > 0 ? sprintf(' : +%d%%', $pct) : sprintf(' : %d%%', $pct);
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
        return $text === '' ? [] : [$text];
    }
}

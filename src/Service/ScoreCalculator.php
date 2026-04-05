<?php

namespace App\Service;

use App\Entity\Agent;
use App\Repository\AgentRepository;

class ScoreCalculator
{
    // Seuils de mapping score brut → niveau 1-4
    const SEUILS = [
        1 => 0,   // Normale  : score brut = 0
        2 => 1,   // Attention : score brut 1-3
        3 => 4,   // Alerte    : score brut 4-7
        4 => 8,   // Critique  : score brut ≥ 8
    ];

    const LABELS = [
        1 => 'Normale',
        2 => 'Attention',
        3 => 'Alerte',
        4 => 'Critique',
    ];

    const BADGES = [
        1 => 'bg-success',
        2 => 'bg-info text-dark',
        3 => 'bg-warning text-dark',
        4 => 'bg-danger',
    ];

    public function __construct(private readonly AgentRepository $agentRepository) {}

    /**
     * Score brut (somme pondérée des incidents des 90 derniers jours).
     * faible=1, moyen=2, grave=3
     */
    public function getRawScore(Agent $agent): int
    {
        return $this->agentRepository->calculateScore($agent);
    }

    /**
     * Score normalisé 1-4.
     */
    public function calculate(Agent $agent): int
    {
        return $this->mapToLevel($this->getRawScore($agent));
    }

    /**
     * Mappe un score brut en niveau 1-4.
     */
    public function mapToLevel(int $rawScore): int
    {
        if ($rawScore >= 8) return 4;
        if ($rawScore >= 4) return 3;
        if ($rawScore >= 1) return 2;
        return 1;
    }

    public function getLevel(int $score): string
    {
        return match($score) {
            4 => 'critique',
            3 => 'alerte',
            2 => 'attention',
            default => 'normale',
        };
    }

    public function getBadgeClass(int $score): string
    {
        return self::BADGES[$score] ?? 'bg-secondary';
    }

    public function getLevelLabel(int $score): string
    {
        return self::LABELS[$score] ?? 'Inconnue';
    }
}

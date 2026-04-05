<?php

namespace App\Service;

use App\Entity\CommentaireSignalement;
use App\Entity\Signalement;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;

class AiPreTriageService
{
    public function __construct(
        private readonly AiConfigurationManager $configurationManager,
        private readonly AiSignalementAnalyzer $analyzer,
        private readonly UserRepository $userRepository,
        private readonly EntityManagerInterface $em,
        private readonly AuditLogger $auditLogger,
    ) {}

    public function preTriage(Signalement $signalement): bool
    {
        if ($this->configurationManager->getActiveFailoverChain() === []) {
            return false;
        }

        try {
            $analysis = $this->analyzer->analyze($signalement, $signalement->getCreatedBy());
        } catch (\Throwable $e) {
            $this->auditLogger->log(
                'signalement.ai.pretriage.failed',
                sprintf('Pré-tri IA échoué pour le signalement #%d.', $signalement->getId()),
                ['error' => $e->getMessage()],
                $signalement,
                $signalement->getCreatedBy()
            );

            return false;
        }

        $author = $this->userRepository->findFirstSystemUser()
            ?? $signalement->getCreatedBy();

        if ($signalement->getType() === 'incident' && $signalement->getGravite() === null) {
            $signalement->setGravite($this->mapDecisionScoreToSeverity((int) ($analysis['decisionScore'] ?? 1)));
        }

        $comment = (new CommentaireSignalement())
            ->setSignalement($signalement)
            ->setUser($author)
            ->setContenu($this->buildComment($analysis));
        $this->em->persist($comment);
        $this->auditLogger->log(
            'signalement.ai.pretriage.created',
            sprintf('Pré-tri IA généré pour le signalement #%d.', $signalement->getId()),
            [
                'provider' => $analysis['provider'] ?? null,
                'usedFallback' => $analysis['usedFallback'] ?? false,
                'recommendedStatus' => $analysis['recommendedStatus'] ?? null,
                'decisionScore' => $analysis['decisionScore'] ?? null,
            ],
            $signalement,
            $author
        );

        return true;
    }

    private function mapDecisionScoreToSeverity(int $score): string
    {
        return match (true) {
            $score >= 4 => 'grave',
            $score >= 3 => 'moyen',
            default => 'faible',
        };
    }

    private function buildComment(array $analysis): string
    {
        $lines = [
            'Pre-tri IA automatique',
            'Fournisseur : ' . ($analysis['provider'] ?? 'N/A'),
            'Decision recommandee : ' . ($analysis['recommendedDecision'] ?? 'N/A'),
            'Urgence : ' . ($analysis['urgencyLevel'] ?? 'N/A'),
            'Statut suggere : ' . ($analysis['recommendedStatus'] ?? 'N/A'),
            'Score de priorite : ' . (($analysis['decisionScore'] ?? 'N/A')) . '/4',
            '',
            'Synthese :',
            (string) ($analysis['summary'] ?? 'Aucune synthese.'),
        ];

        if (!empty($analysis['recommendedActions']) && is_array($analysis['recommendedActions'])) {
            $lines[] = '';
            $lines[] = 'Actions recommandees :';
            foreach ($analysis['recommendedActions'] as $action) {
                $lines[] = '- ' . $action;
            }
        }

        if (!empty($analysis['usedFallback'])) {
            $lines[] = '';
            $lines[] = 'Resilience : bascule automatique utilisee.';
        }

        return implode("\n", $lines);
    }
}

<?php

namespace App\Tests\Service;

use App\Entity\AiProviderConfig;
use App\Entity\Signalement;
use App\Service\AiConfigurationManager;
use App\Service\AiFailoverRouter;
use App\Service\AiPromptBuilder;
use App\Service\AiSignalementAnalyzer;
use PHPUnit\Framework\TestCase;

class AiSignalementAnalyzerTest extends TestCase
{
    public function testAnalyzerNormalizesDecisionPayload(): void
    {
        $config = (new AiProviderConfig())
            ->setName('OpenRouter Réel')
            ->setModel('openai/gpt-4o-mini')
            ->setProviderType('openrouter');

        $configurationManager = $this->createMock(AiConfigurationManager::class);
        $configurationManager
            ->method('getDefaultActiveConfig')
            ->willReturn($config);
        $configurationManager
            ->method('getActiveFailoverChain')
            ->willReturn([$config]);

        $promptBuilder = $this->createMock(AiPromptBuilder::class);
        $promptBuilder
            ->method('buildSignalementAnalysisMessages')
            ->willReturn([['role' => 'user', 'content' => 'Test']]);

        $router = $this->createMock(AiFailoverRouter::class);
        $router
            ->method('chat')
            ->willReturn([
                'config' => $config,
                'raw' => json_encode([
                'summary' => 'Résumé',
                'qualification' => 'Incident confirmé',
                'risk_factors' => ['plainte', 'vidéo'],
                'recommended_actions' => 'Informer RH immédiatement',
                'courrier_guidance' => 'Rédiger un courrier factuel.',
                'recommended_decision' => 'Passage en validation RH',
                'urgency_level' => 'élevée',
                'recommended_status' => 'validation',
                'decision_score' => 5,
                'video_preservation_action' => 'Conserver les images.',
                'alert_email_subject' => 'Alerte dossier critique',
                'alert_email_body' => 'Merci de traiter sous 2h.',
                'alert_target_roles' => ['manager', 'ROLE_RH'],
                'courrier_draft' => 'Projet de courrier',
                ], JSON_THROW_ON_ERROR),
                'attempts' => [['name' => 'OpenRouter Réel', 'status' => 'success']],
                'usedFallback' => false,
            ]);

        $analyzer = new AiSignalementAnalyzer($configurationManager, $promptBuilder, $router);
        $signalement = (new Signalement())
            ->setType('incident')
            ->setCanal('formulaire')
            ->setTitre('Test')
            ->setDescription('Description')
            ->setDateFait(new \DateTimeImmutable())
            ->setStatut('nouveau');

        $analysis = $analyzer->analyze($signalement);

        self::assertSame(4, $analysis['decisionScore']);
        self::assertSame(['Informer RH immédiatement'], $analysis['recommendedActions']);
        self::assertSame(['ROLE_MANAGER', 'ROLE_RH'], $analysis['alertTargetRoles']);
        self::assertSame('Projet de courrier', $analysis['courrierDraft']);
    }
}

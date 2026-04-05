<?php

namespace App\Tests\Service;

use App\Entity\AiProviderConfig;
use App\Entity\Signalement;
use App\Service\AiPromptBuilder;
use PHPUnit\Framework\TestCase;

class AiPromptBuilderTest extends TestCase
{
    public function testPromptBuilderInjectsDecisionSupportContext(): void
    {
        $config = (new AiProviderConfig())
            ->setName('Test')
            ->setModel('demo-model')
            ->setProviderType('openrouter');

        $signalement = (new Signalement())
            ->setType('incident')
            ->setCanal('formulaire')
            ->setTitre('Incident test')
            ->setDescription('Description test')
            ->setDateFait(new \DateTimeImmutable('-2 hours'))
            ->setStatut('nouveau')
            ->setSourceLine('72')
            ->setSourceVehicle('4589')
            ->setVoiceTranscript('Transcript test')
            ->setTranslatedDescription('Traduction test');

        $messages = (new AiPromptBuilder())->buildSignalementAnalysisMessages($config, $signalement);

        self::assertCount(2, $messages);
        self::assertStringContainsString('Vidéo', $messages[1]['content']);
        self::assertStringContainsString('Transcript test', $messages[1]['content']);
        self::assertStringContainsString('Traduction test', $messages[1]['content']);
        self::assertStringContainsString('email d\'alerte', $messages[1]['content']);
        self::assertStringContainsString('score de priorité', $messages[1]['content']);
    }
}

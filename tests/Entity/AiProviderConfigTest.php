<?php

namespace App\Tests\Entity;

use App\Entity\AiProviderConfig;
use PHPUnit\Framework\TestCase;

class AiProviderConfigTest extends TestCase
{
    public function testResolvedPathsMatchProviderDefaults(): void
    {
        $config = (new AiProviderConfig())->setName('Test')->setModel('x');

        $config->setProviderType('openrouter');
        self::assertSame('/api/v1/chat/completions', $config->getResolvedApiPath());

        $config->setProviderType('gemini');
        self::assertSame('/v1beta/models/{model}:generateContent', $config->getResolvedApiPath());

        $config->setProviderType('ollama_cloud');
        self::assertSame('/api/chat', $config->getResolvedApiPath());
    }
}

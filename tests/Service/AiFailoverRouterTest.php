<?php

namespace App\Tests\Service;

use App\Entity\AiProviderConfig;
use App\Service\AiConfigurationManager;
use App\Service\AiFailoverRouter;
use App\Service\AiGateway;
use App\Service\AuditLogger;
use PHPUnit\Framework\TestCase;

class AiFailoverRouterTest extends TestCase
{
    public function testRouterFallsBackToSecondProviderOnRetryableError(): void
    {
        $primary = (new AiProviderConfig())
            ->setName('Primary')
            ->setVendorLabel('OpenRouter')
            ->setModel('m1');
        $secondary = (new AiProviderConfig())
            ->setName('Secondary')
            ->setVendorLabel('Gemini')
            ->setModel('m2');

        $configurationManager = $this->createMock(AiConfigurationManager::class);
        $configurationManager
            ->method('getActiveFailoverChain')
            ->willReturn([$primary, $secondary]);

        $gateway = $this->createMock(AiGateway::class);
        $gateway
            ->method('chat')
            ->willReturnCallback(function (AiProviderConfig $config) {
                if ($config->getName() === 'Primary') {
                    throw new \RuntimeException('429 rate limit exceeded');
                }

                return '{"ok":true}';
            });

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                'ai.failover.used',
                self::stringContains('Secondary'),
                self::arrayHasKey('attempts'),
            );

        $router = new AiFailoverRouter($configurationManager, $gateway, $auditLogger);
        $result = $router->chat([['role' => 'user', 'content' => 'test']]);

        self::assertTrue($result['usedFallback']);
        self::assertSame('Secondary', $result['config']->getName());
        self::assertCount(2, $result['attempts']);
    }

    public function testRouterStopsOnNonRetryableError(): void
    {
        $primary = (new AiProviderConfig())
            ->setName('Primary')
            ->setVendorLabel('OpenRouter')
            ->setModel('m1');
        $secondary = (new AiProviderConfig())
            ->setName('Secondary')
            ->setVendorLabel('Gemini')
            ->setModel('m2');

        $configurationManager = $this->createMock(AiConfigurationManager::class);
        $configurationManager
            ->method('getActiveFailoverChain')
            ->willReturn([$primary, $secondary]);

        $gateway = $this->createMock(AiGateway::class);
        $gateway
            ->expects(self::once())
            ->method('chat')
            ->willThrowException(new \RuntimeException('401 invalid api key'));

        $auditLogger = $this->createMock(AuditLogger::class);
        $auditLogger
            ->expects(self::once())
            ->method('log')
            ->with(
                'ai.failover.failed',
                self::stringContains('chaîne'),
                self::arrayHasKey('attempts'),
            );

        $router = new AiFailoverRouter($configurationManager, $gateway, $auditLogger);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('401 invalid api key');
        $router->chat([['role' => 'user', 'content' => 'test']]);
    }
}

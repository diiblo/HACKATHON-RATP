<?php

namespace App\Tests\Service;

use App\Service\AiSecretManager;
use PHPUnit\Framework\TestCase;

class AiSecretManagerTest extends TestCase
{
    public function testEncryptDecryptRoundTrip(): void
    {
        $manager = new AiSecretManager('test-secret', 'test');
        $encrypted = $manager->encrypt('sk-demo-secret');

        self::assertNotSame('sk-demo-secret', $encrypted);
        self::assertSame('sk-demo-secret', $manager->decrypt($encrypted));
    }

    public function testDefaultPlaceholderSecretIsRejectedOutsideDevAndTest(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('APP_SECRET par défaut interdit hors dev/test');

        new AiSecretManager('change-this-local-dev-secret', 'prod');
    }
}

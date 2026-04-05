<?php

namespace App\Tests\Service;

use App\Repository\AgentRepository;
use App\Service\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function testMapToLevelUsesConfiguredThresholds(): void
    {
        $repository = $this->createStub(AgentRepository::class);
        $calculator = new ScoreCalculator($repository);

        self::assertSame(1, $calculator->mapToLevel(0));
        self::assertSame(2, $calculator->mapToLevel(1));
        self::assertSame(2, $calculator->mapToLevel(3));
        self::assertSame(3, $calculator->mapToLevel(4));
        self::assertSame(3, $calculator->mapToLevel(7));
        self::assertSame(4, $calculator->mapToLevel(8));
    }
}

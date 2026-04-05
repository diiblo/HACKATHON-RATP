<?php

namespace App\Tests\Service;

use App\Service\PdfGenerator;
use PHPUnit\Framework\TestCase;

class PdfGeneratorTest extends TestCase
{
    public function testGenerateFromTextCreatesMultiplePagesWhenNeeded(): void
    {
        $generator = new PdfGenerator();
        $content = implode("\n", array_fill(0, 120, 'Ligne de test longue pour valider la pagination du PDF.'));

        $pdf = $generator->generateFromText('Courrier de test', $content);

        self::assertMatchesRegularExpression('/\/Count [2-9][0-9]*/', $pdf);
        self::assertStringContainsString('Courrier de test \\(suite\\)', $pdf);
    }

    public function testGenerateFromTextTransliteratesUnsupportedCharacters(): void
    {
        $generator = new PdfGenerator();

        $pdf = $generator->generateFromText('Titre 🚍', "Texte avec emoji 🚍 et accents éèà.");

        self::assertStringContainsString('Titre ?', $pdf);
        self::assertStringContainsString('accents', $pdf);
    }
}

<?php

namespace App\Service;

class LanguageSimulationService
{
    public function normalize(?string $language, string $filename = ''): string
    {
        $language = strtolower(trim((string) $language));
        if (in_array($language, ['fr', 'en', 'es', 'ar'], true)) {
            return $language;
        }

        $filename = strtolower($filename);
        foreach (['[en]' => 'en', '[es]' => 'es', '[ar]' => 'ar', '[fr]' => 'fr'] as $marker => $code) {
            if (str_contains($filename, $marker)) {
                return $code;
            }
        }

        return 'fr';
    }

    public function getLabel(string $language): string
    {
        return match ($language) {
            'en' => 'Anglais',
            'es' => 'Espagnol',
            'ar' => 'Arabe',
            default => 'Français',
        };
    }

    public function translateToFrench(string $text, string $language): string
    {
        if ($language === 'fr') {
            return $text;
        }

        return sprintf('[Traduction simulée depuis %s] %s', $this->getLabel($language), $text);
    }
}

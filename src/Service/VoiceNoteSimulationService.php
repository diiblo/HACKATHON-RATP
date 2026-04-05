<?php

namespace App\Service;

use App\Entity\Signalement;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class VoiceNoteSimulationService
{
    public function __construct(
        private readonly LanguageSimulationService $languageSimulationService,
    ) {}

    public function process(UploadedFile $file, Signalement $signalement, ?string $languageHint): array
    {
        $language = $this->languageSimulationService->normalize($languageHint, $file->getClientOriginalName());
        $context = $signalement->getSourceContextLabel() ?? 'contexte non spécifié';
        $transcript = sprintf(
            'Transcription simulée de la note vocale "%s" : signalement concernant %s. Faits saillants repris pour %s.',
            $file->getClientOriginalName(),
            $signalement->getAgentDisplayName(),
            $context
        );

        return [
            'language' => $language,
            'transcript' => $transcript,
            'translation' => $this->languageSimulationService->translateToFrench($transcript, $language),
        ];
    }
}

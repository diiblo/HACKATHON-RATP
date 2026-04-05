<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiVoiceProcessingService
{
    public function __construct(
        private readonly AiConfigurationManager $configurationManager,
        private readonly AiSecretManager $secretManager,
        private readonly HttpClientInterface $httpClient,
    ) {}

    public function hasRealProvider(): bool
    {
        return $this->findGeminiConfig() !== null;
    }

    public function process(UploadedFile $file, ?string $languageHint = null): array
    {
        $config = $this->findGeminiConfig();
        if ($config === null) {
            throw new \RuntimeException('Aucun fournisseur Gemini actif n\'est disponible pour le traitement audio.');
        }

        $apiKey = $this->secretManager->decrypt($config->getApiKeyEncrypted());
        if ($apiKey === null || $apiKey === '') {
            throw new \RuntimeException('La configuration Gemini active ne contient pas de clé API exploitable.');
        }

        $mimeType = $file->getMimeType() ?: 'audio/mpeg';
        $audioBytes = file_get_contents($file->getPathname());
        if ($audioBytes === false) {
            throw new \RuntimeException('Lecture du fichier audio impossible.');
        }

        $path = str_replace('{model}', $config->getModel(), $config->getResolvedApiPath());
        $url = rtrim((string) $config->getApiBaseUrl(), '/') . $path;
        $url .= (str_contains($url, '?') ? '&' : '?') . 'key=' . urlencode($apiKey);

        $prompt = <<<TXT
Analyse ce message vocal d'un usager dans le contexte d'un signalement RATP.
Retourne strictement un objet JSON avec les cles suivantes :
transcript, language_code, translation_fr, summary.

Contraintes :
- transcript : transcription fidele de la parole.
- language_code : code de langue ISO court comme fr, en, es, ar.
- translation_fr : traduction en francais, ou le transcript si la langue est deja le francais.
- summary : resume concis des faits utiles pour qualifier le dossier.
- Si un indice de langue est fourni, utilise-le comme aide sans l'imposer aveuglement.

Indice de langue declare par l'utilisateur : %s
TXT;

        $response = $this->httpClient->request('POST', $url, [
            'timeout' => max(30, $config->getTimeoutSeconds()),
            'headers' => ['Content-Type' => 'application/json'],
            'json' => [
                'contents' => [[
                    'parts' => [
                        ['text' => sprintf($prompt, $languageHint ?: 'aucun')],
                        [
                            'inline_data' => [
                                'mime_type' => $mimeType,
                                'data' => base64_encode($audioBytes),
                            ],
                        ],
                    ],
                ]],
                'generation_config' => [
                    'response_mime_type' => 'application/json',
                    'temperature' => 0.1,
                ],
            ],
        ])->toArray(false);

        $raw = $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
        $parsed = json_decode(trim($raw), true);
        if (!is_array($parsed)) {
            throw new \RuntimeException('Réponse Gemini audio non exploitable.');
        }

        $transcript = trim((string) ($parsed['transcript'] ?? ''));
        $languageCode = strtolower(trim((string) ($parsed['language_code'] ?? ($languageHint ?: 'fr'))));
        $translation = trim((string) ($parsed['translation_fr'] ?? $transcript));
        $summary = trim((string) ($parsed['summary'] ?? ''));

        if ($transcript === '') {
            throw new \RuntimeException('Aucune transcription exploitable n’a été produite.');
        }

        return [
            'provider' => $config->getDisplayLabel(),
            'transcript' => $transcript,
            'language' => $languageCode !== '' ? $languageCode : 'fr',
            'translation' => $translation !== '' ? $translation : $transcript,
            'summary' => $summary,
        ];
    }

    private function findGeminiConfig(): ?AiProviderConfig
    {
        foreach ($this->configurationManager->getActiveFailoverChain() as $config) {
            if ($config->getProviderType() === 'gemini' && $config->hasApiKey()) {
                return $config;
            }
        }

        return null;
    }
}

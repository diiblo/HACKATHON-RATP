<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class AiGateway
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly AiSecretManager $secretManager,
    ) {}

    public function chat(AiProviderConfig $config, array $messages): string
    {
        return match ($config->getProviderType()) {
            'gemini' => $this->chatWithGemini($config, $messages),
            'ollama' => $this->chatWithOllama($config, $messages),
            'ollama_cloud' => $this->chatWithOllama($config, $messages),
            default => $this->chatWithOpenAiCompatible($config, $messages),
        };
    }

    private function chatWithOpenAiCompatible(AiProviderConfig $config, array $messages): string
    {
        $url = rtrim((string) $config->getApiBaseUrl(), '/') . $config->getResolvedApiPath();
        $headers = $this->buildHeaders($config, true);
        $apiKey = $this->secretManager->decrypt($config->getApiKeyEncrypted());
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = $this->httpClient->request('POST', $url, [
            'timeout' => $config->getTimeoutSeconds(),
            'headers' => $headers,
            'json' => [
                'model' => $config->getModel(),
                'temperature' => $config->getTemperature(),
                'response_format' => ['type' => 'json_object'],
                'messages' => $messages,
            ],
        ])->toArray();

        return $response['choices'][0]['message']['content'] ?? '';
    }

    private function chatWithOllama(AiProviderConfig $config, array $messages): string
    {
        $url = rtrim((string) $config->getApiBaseUrl(), '/') . $config->getResolvedApiPath();
        $headers = $this->buildHeaders($config, true);
        $apiKey = $this->secretManager->decrypt($config->getApiKeyEncrypted());
        if ($apiKey !== null) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        }

        $response = $this->httpClient->request('POST', $url, [
            'timeout' => $config->getTimeoutSeconds(),
            'headers' => $headers,
            'json' => [
                'model' => $config->getModel(),
                'stream' => false,
                'format' => 'json',
                'messages' => $messages,
                'options' => [
                    'temperature' => $config->getTemperature(),
                ],
            ],
        ])->toArray();

        return $response['message']['content'] ?? '';
    }

    private function chatWithGemini(AiProviderConfig $config, array $messages): string
    {
        $apiKey = $this->secretManager->decrypt($config->getApiKeyEncrypted());
        $path = str_replace('{model}', $config->getModel(), $config->getResolvedApiPath());
        $url = rtrim((string) $config->getApiBaseUrl(), '/') . $path;
        if ($apiKey !== null) {
            $url .= (str_contains($url, '?') ? '&' : '?') . 'key=' . urlencode($apiKey);
        }

        $parts = [];
        foreach ($messages as $message) {
            $parts[] = [
                'role' => $message['role'] === 'assistant' ? 'model' : 'user',
                'parts' => [['text' => (string) ($message['content'] ?? '')]],
            ];
        }

        $response = $this->httpClient->request('POST', $url, [
            'timeout' => $config->getTimeoutSeconds(),
            'headers' => $this->buildHeaders($config, true),
            'json' => [
                'contents' => $parts,
                'generationConfig' => [
                    'temperature' => $config->getTemperature(),
                    'responseMimeType' => 'application/json',
                ],
            ],
        ])->toArray();

        return $response['candidates'][0]['content']['parts'][0]['text'] ?? '';
    }

    private function buildHeaders(AiProviderConfig $config, bool $json = true): array
    {
        $headers = $json ? ['Content-Type' => 'application/json'] : [];
        $raw = trim((string) $config->getExtraHeaders());
        if ($raw === '') {
            return $headers;
        }

        foreach (preg_split('/\R/u', $raw) ?: [] as $line) {
            $line = trim($line);
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $headers[trim($name)] = trim($value);
        }

        return $headers;
    }
}

<?php

namespace App\Service;

class AiSecretManager
{
    private const DEFAULT_DEV_SECRET = 'change-this-local-dev-secret';

    private string $key;

    public function __construct(string $appSecret, string $kernelEnvironment)
    {
        if ($appSecret === self::DEFAULT_DEV_SECRET && !in_array($kernelEnvironment, ['dev', 'test'], true)) {
            throw new \RuntimeException('APP_SECRET par défaut interdit hors dev/test. Configurez un secret applicatif réel.');
        }

        $this->key = hash('sha256', $appSecret, true);
    }

    public function encrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $iv = random_bytes(16);
        $ciphertext = openssl_encrypt($value, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);
        if ($ciphertext === false) {
            throw new \RuntimeException('Impossible de chiffrer la clé API.');
        }

        return base64_encode($iv . $ciphertext);
    }

    public function decrypt(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $payload = base64_decode($value, true);
        if ($payload === false || strlen($payload) < 17) {
            return null;
        }

        $iv = substr($payload, 0, 16);
        $ciphertext = substr($payload, 16);
        $plaintext = openssl_decrypt($ciphertext, 'AES-256-CBC', $this->key, OPENSSL_RAW_DATA, $iv);

        return $plaintext === false ? null : $plaintext;
    }
}

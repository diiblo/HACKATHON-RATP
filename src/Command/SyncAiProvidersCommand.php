<?php

namespace App\Command;

use App\Entity\AiProviderConfig;
use App\Service\AiConfigurationManager;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:sync-real-providers',
    description: 'Crée ou met à jour les connecteurs IA réels à partir des variables d’environnement locales.',
)]
class SyncAiProvidersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiConfigurationManager $configurationManager,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('activate', null, InputOption::VALUE_REQUIRED, 'Nom interne du fournisseur à rendre par défaut', 'OpenRouter Réel');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $activate = trim((string) $input->getOption('activate'));

        $providers = [
            [
                'name' => 'OpenRouter Réel',
                'providerType' => 'openrouter',
                'vendorLabel' => 'OpenRouter',
                'apiBaseUrl' => $_ENV['AI_OPENROUTER_BASE_URL'] ?? 'https://openrouter.ai',
                'apiPath' => $_ENV['AI_OPENROUTER_API_PATH'] ?? '/api/v1/chat/completions',
                'model' => $_ENV['AI_OPENROUTER_MODEL'] ?? 'openai/gpt-4o-mini',
                'apiKey' => $_ENV['AI_OPENROUTER_API_KEY'] ?? null,
                'extraHeaders' => trim((string) ($_ENV['AI_OPENROUTER_EXTRA_HEADERS'] ?? "HTTP-Referer: https://ratp.local\nX-Title: SIGNAL v2 RATP")),
            ],
            [
                'name' => 'Gemini Réel',
                'providerType' => 'gemini',
                'vendorLabel' => 'Google Gemini',
                'apiBaseUrl' => $_ENV['AI_GEMINI_BASE_URL'] ?? 'https://generativelanguage.googleapis.com',
                'apiPath' => $_ENV['AI_GEMINI_API_PATH'] ?? '/v1beta/models/{model}:generateContent',
                'model' => $_ENV['AI_GEMINI_MODEL'] ?? 'gemini-2.0-flash',
                'apiKey' => $_ENV['AI_GEMINI_API_KEY'] ?? null,
                'extraHeaders' => trim((string) ($_ENV['AI_GEMINI_EXTRA_HEADERS'] ?? '')),
            ],
            [
                'name' => 'Ollama Cloud Réel',
                'providerType' => 'ollama_cloud',
                'vendorLabel' => 'Ollama Cloud',
                'apiBaseUrl' => $_ENV['AI_OLLAMA_CLOUD_BASE_URL'] ?? 'https://ollama.com/api',
                'apiPath' => $_ENV['AI_OLLAMA_CLOUD_API_PATH'] ?? '/chat',
                'model' => $_ENV['AI_OLLAMA_CLOUD_MODEL'] ?? 'llama3.1',
                'apiKey' => $_ENV['AI_OLLAMA_CLOUD_API_KEY'] ?? null,
                'extraHeaders' => trim((string) ($_ENV['AI_OLLAMA_CLOUD_EXTRA_HEADERS'] ?? '')),
            ],
        ];

        foreach ($providers as $providerData) {
            if (trim((string) $providerData['apiKey']) === '') {
                $io->warning(sprintf('Clé absente pour %s, configuration ignorée.', $providerData['name']));
                continue;
            }

            $config = $this->em->getRepository(AiProviderConfig::class)->findOneBy(['name' => $providerData['name']]) ?? new AiProviderConfig();
            $config
                ->setName($providerData['name'])
                ->setProviderType($providerData['providerType'])
                ->setVendorLabel($providerData['vendorLabel'])
                ->setApiBaseUrl($providerData['apiBaseUrl'])
                ->setApiPath($providerData['apiPath'])
                ->setModel($providerData['model'])
                ->setTemperature((float) ($_ENV['AI_DEFAULT_TEMPERATURE'] ?? 0.2))
                ->setTimeoutSeconds((int) ($_ENV['AI_DEFAULT_TIMEOUT'] ?? 30))
                ->setExtraHeaders($providerData['extraHeaders'])
                ->setActive(true)
                ->setIsDefault($providerData['name'] === $activate);

            $this->configurationManager->save($config, $providerData['apiKey'], null);
            $io->success(sprintf('%s synchronisé.', $providerData['name']));
        }

        return Command::SUCCESS;
    }
}

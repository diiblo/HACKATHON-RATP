<?php

namespace App\Command;

use App\Repository\AiProviderConfigRepository;
use App\Service\AiFailoverRouter;
use App\Service\AiGateway;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:test-provider',
    description: 'Teste un connecteur IA configuré depuis la ligne de commande.',
)]
class TestAiProviderCommand extends Command
{
    public function __construct(
        private readonly AiProviderConfigRepository $repository,
        private readonly AiGateway $gateway,
        private readonly AiFailoverRouter $failoverRouter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::OPTIONAL, 'Nom interne de la configuration')
            ->addOption('failover', null, InputOption::VALUE_NONE, 'Teste la chaîne de secours automatique sur les connecteurs actifs')
            ->addOption('prompt', null, InputOption::VALUE_REQUIRED, 'Prompt de test', 'Réponds en JSON avec {"status":"ok","message":"connecteur opérationnel"}');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $messages = [
            ['role' => 'system', 'content' => 'Tu es un assistant de diagnostic. Réponds brièvement en JSON.'],
            ['role' => 'user', 'content' => (string) $input->getOption('prompt')],
        ];

        if ($input->getOption('failover')) {
            try {
                $result = $this->failoverRouter->chat($messages);
            } catch (\Throwable $e) {
                $io->error('Échec du test failover : ' . $e->getMessage());
                return Command::FAILURE;
            }

            $io->success(sprintf('Chaîne failover opérationnelle via %s.', $result['config']->getName()));
            foreach ($result['attempts'] as $attempt) {
                $io->writeln(sprintf(
                    '- %s: %s%s',
                    $attempt['name'],
                    $attempt['status'],
                    isset($attempt['error']) ? ' (' . $attempt['error'] . ')' : ''
                ));
            }
            $io->writeln($result['raw']);

            return Command::SUCCESS;
        }

        $config = $name
            ? $this->repository->findOneBy(['name' => $name])
            : $this->repository->findDefaultActive();

        if ($config === null) {
            $io->error('Aucune configuration IA correspondante.');
            return Command::FAILURE;
        }

        try {
            $raw = $this->gateway->chat($config, $messages);
        } catch (\Throwable $e) {
            $io->error('Échec du test : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->success(sprintf('Connecteur %s opérationnel.', $config->getName()));
        $io->writeln($raw);

        return Command::SUCCESS;
    }
}

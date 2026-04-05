<?php

namespace App\Command;

use App\Entity\Signalement;
use App\Service\AiSignalementAnalyzer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:ai:analyze-signalement',
    description: 'Exécute l’analyse IA complète sur un signalement existant.',
)]
class AiAnalyzeSignalementCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly AiSignalementAnalyzer $analyzer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('id', InputArgument::OPTIONAL, 'Identifiant du signalement à analyser');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $id = $input->getArgument('id');

        $signalement = $id
            ? $this->em->getRepository(Signalement::class)->find((int) $id)
            : $this->em->getRepository(Signalement::class)->findOneBy([], ['id' => 'ASC']);

        if (!$signalement instanceof Signalement) {
            $io->error('Aucun signalement trouvé.');
            return Command::FAILURE;
        }

        try {
            $analysis = $this->analyzer->analyze($signalement);
        } catch (\Throwable $e) {
            $io->error('Échec de l’analyse IA : ' . $e->getMessage());
            return Command::FAILURE;
        }

        $io->title(sprintf('Analyse IA du signalement #%d', $signalement->getId()));
        $io->definitionList(
            ['Titre' => $signalement->getTitre()],
            ['Fournisseur' => $analysis['provider']],
            ['Décision' => $analysis['recommendedDecision']],
            ['Urgence' => $analysis['urgencyLevel']],
            ['Statut suggéré' => $analysis['recommendedStatus']],
            ['Score IA' => sprintf('%d/4', $analysis['decisionScore'])],
            ['Alerte' => $analysis['alertEmailSubject']]
        );
        $io->section('Résumé');
        $io->writeln($analysis['summary']);
        $io->section('Actions recommandées');
        $io->listing($analysis['recommendedActions']);

        return Command::SUCCESS;
    }
}

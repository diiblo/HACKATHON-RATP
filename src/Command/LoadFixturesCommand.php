<?php

namespace App\Command;

use App\Entity\AiProviderConfig;
use App\Entity\Agent;
use App\Entity\CommentaireSignalement;
use App\Entity\CourrierDraft;
use App\Entity\HistoriqueStatut;
use App\Entity\PieceJointe;
use App\Entity\Signalement;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:load-fixtures',
    description: 'Charge les données de démo pour le MVP RATP',
)]
class LoadFixturesCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $hasher,
        private readonly string $uploadDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Vider les tables avant chargement');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $io->title('Chargement des fixtures RATP MVP');

        if ($input->getOption('reset')) {
            $io->section('Nettoyage des données existantes');
            $connection = $this->em->getConnection();
            $connection->executeStatement('SET session_replication_role = replica');
            foreach (['ai_provider_config', 'piece_jointe', 'courrier_draft', 'commentaire_signalement', 'historique_statut', 'signalement', 'agent', 'app_user'] as $table) {
                $connection->executeStatement("TRUNCATE TABLE $table CASCADE");
                $io->writeln("  <comment>Vidé :</comment> $table");
            }
            $connection->executeStatement('SET session_replication_role = DEFAULT');
            $this->clearUploadDirectory();
            $io->success('Tables vidées.');
        }

        // === USERS ===
        $io->section('Création des utilisateurs');
        $usersData = [
            ['admin@ratp.fr',      'Admin1234!',  ['ROLE_ADMIN'],    'Dupont',   'Sophie', 'Direction'],
            ['devadmin@ratp.fr',   'DevAdmin1!',  ['ROLE_ADMIN', 'ROLE_DEV'], 'Robert', 'Nina', 'DSI Dev'],
            ['manager1@ratp.fr',   'Manager1!',   ['ROLE_MANAGER'],  'Martin',   'Paul',   'Centre A'],
            ['manager2@ratp.fr',   'Manager1!',   ['ROLE_MANAGER'],  'Leroy',    'Claire', 'Centre B'],
            ['rh1@ratp.fr',        'Rh12345!',    ['ROLE_RH'],       'Bernard',  'Hélène', 'RH Central'],
            ['rh2@ratp.fr',        'Rh12345!',    ['ROLE_RH'],       'Rousseau', 'Marc',   'RH Sud'],
            ['juridique1@ratp.fr', 'Juri123!',    ['ROLE_JURIDIQUE'],'Moreau',   'Isabelle','Juridique'],
            ['juridique2@ratp.fr', 'Juri123!',    ['ROLE_JURIDIQUE'],'Simon',    'Jean',   'Juridique'],
            ['viewer@ratp.fr',     'View123!',    ['ROLE_USER'],     'Blanc',    'Thomas', 'Centre C'],
        ];

        $users = [];
        foreach ($usersData as [$email, $plain, $roles, $nom, $prenom, $centre]) {
            // Skip if already exists
            $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
            if ($existing) {
                $users[$email] = $existing;
                $io->writeln("  <comment>Déjà existant :</comment> $email");
                continue;
            }
            $user = new User();
            $user->setEmail($email)
                ->setRoles($roles)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setCentre($centre)
                ->setActif(true)
                ->setPassword($this->hasher->hashPassword(new User(), $plain));
            // Re-hash with the actual user object
            $user->setPassword($this->hasher->hashPassword($user, $plain));
            $this->em->persist($user);
            $users[$email] = $user;
            $io->writeln("  <info>Créé :</info> $email ($prenom $nom)");
        }
        $this->em->flush();

        $io->section('Création des configurations IA');
        $aiConfigs = [
            [
                'name' => 'Ollama Local Démo',
                'vendor' => 'Ollama',
                'type' => 'ollama',
                'base' => 'http://host.docker.internal:11434',
                'path' => '/api/chat',
                'model' => 'llama3.1',
                'active' => false,
                'default' => true,
            ],
            [
                'name' => 'OpenRouter Démo',
                'vendor' => 'OpenRouter',
                'type' => 'openrouter',
                'base' => 'https://openrouter.ai',
                'path' => '/api/v1/chat/completions',
                'model' => 'openai/gpt-4o-mini',
                'active' => false,
                'default' => false,
            ],
            [
                'name' => 'Gemini Démo',
                'vendor' => 'Google Gemini',
                'type' => 'gemini',
                'base' => 'https://generativelanguage.googleapis.com',
                'path' => '/v1beta/models/{model}:generateContent',
                'model' => 'gemini-2.0-flash',
                'active' => false,
                'default' => false,
            ],
            [
                'name' => 'Ollama Cloud Démo',
                'vendor' => 'Ollama Cloud',
                'type' => 'ollama_cloud',
                'base' => 'https://ollama.com/api',
                'path' => '/chat',
                'model' => 'llama3.1',
                'active' => false,
                'default' => false,
            ],
        ];

        foreach ($aiConfigs as $aiData) {
            $existingAi = $this->em->getRepository(AiProviderConfig::class)->findOneBy(['name' => $aiData['name']]);
            if ($existingAi) {
                continue;
            }

            $aiConfig = new AiProviderConfig();
            $aiConfig->setName($aiData['name'])
                ->setVendorLabel($aiData['vendor'])
                ->setProviderType($aiData['type'])
                ->setApiBaseUrl($aiData['base'])
                ->setApiPath($aiData['path'])
                ->setModel($aiData['model'])
                ->setSystemPrompt('Tu es un assistant RATP d\'aide à la décision. Réponds uniquement en JSON structuré.')
                ->setContextTemplate("Titre : {{titre}}\nType : {{type}}\nGravité : {{gravite}}\nCanal : {{canal}}\nAgent : {{agent}}\nStatut : {{statut}}\nDescription : {{description}}\nCommentaires : {{commentaires}}\nTraduction : {{traduction}}\nSource : {{source}}\nVidéo : {{video_timer}}\nPlainte : {{plainte}}")
                ->setActive($aiData['active'])
                ->setIsDefault($aiData['default']);
            $this->em->persist($aiConfig);
            $io->writeln('  <info>Créé :</info> configuration IA ' . $aiData['name']);
        }
        $this->em->flush();

        // === AGENTS ===
        $io->section('Création des agents');
        $agentsData = [
            ['RAT001', 'Dubois',   'Karim',  'Centre A', '1985-03-15'],
            ['RAT002', 'Laurent',  'Amina',  'Centre B', '1990-07-22'],
            ['RAT003', 'Petit',    'Julien', 'Centre A', '1978-11-08'],
            ['RAT004', 'Garcia',   'Sofia',  'Centre C', '1995-02-14'],
            ['RAT005', 'Mercier',  'Pierre', 'Centre B', '1983-06-30'],
        ];

        $agents = [];
        foreach ($agentsData as [$matricule, $nom, $prenom, $centre, $dob]) {
            $existing = $this->em->getRepository(Agent::class)->findOneBy(['matricule' => $matricule]);
            if ($existing) {
                $agents[$matricule] = $existing;
                $io->writeln("  <comment>Déjà existant :</comment> $matricule");
                continue;
            }
            $agent = new Agent();
            $agent->setMatricule($matricule)
                ->setNom($nom)
                ->setPrenom($prenom)
                ->setCentre($centre)
                ->setDateNaissance(new \DateTimeImmutable($dob))
                ->setActif(true);
            $this->em->persist($agent);
            $agents[$matricule] = $agent;
            $io->writeln("  <info>Créé :</info> $matricule — $prenom $nom");
        }
        $this->em->flush();

        // === SIGNALEMENTS ===
        $io->section('Création des signalements');

        $admin    = $users['admin@ratp.fr'];
        $manager1 = $users['manager1@ratp.fr'];
        $manager2 = $users['manager2@ratp.fr'];
        $rh1      = $users['rh1@ratp.fr'];

        $now = new \DateTimeImmutable();
        $signalements = [];

        // Fonction helper
        $mkSig = function(Agent $agent, string $type, string $canal, string $gravite = null,
                           string $titre, string $desc, string $dateFait, string $statut,
                           User $createdBy) use ($now): Signalement {
            $s = new Signalement();
            $s->setAgent($agent)
              ->setType($type)
              ->setCanal($canal)
              ->setTitre($titre)
              ->setDescription($desc)
              ->setDateFait(new \DateTimeImmutable($dateFait))
              ->setStatut($statut)
              ->setCreatedBy($createdBy);
            if ($type === 'incident' && $gravite) {
                $s->setGravite($gravite);
            }
            return $s;
        };

        $signalementsData = [
            // RAT001 Dubois Karim — score ALERTE : 3 grave + 2 moyen dans les 90j = 3×3+2×2=13
            [$agents['RAT001'], 'incident', 'terrain', 'grave',  'Altercation avec voyageur', 'L\'agent a eu une altercation verbale agressive avec un voyageur qui refusait de valider son titre. Des témoins ont confirmé le comportement inapproprié.', '-10 days',  'validation', $manager1],
            [$agents['RAT001'], 'incident', 'email',   'grave',  'Refus de service injustifié', 'Signalement d\'un voyageur indiquant que l\'agent a refusé de l\'aider à composter son titre sans raison valable.', '-25 days',  'qualification', $admin],
            [$agents['RAT001'], 'incident', 'formulaire','grave', 'Propos déplacés enregistrés', 'Une voyageuse rapporte des propos déplacés tenus par l\'agent lors d\'un contrôle. Elle dispose d\'un enregistrement.', '-40 days',  'traite',    $manager1],
            [$agents['RAT001'], 'incident', 'terrain', 'moyen',  'Absence de port de badge', 'L\'agent n\'arborait pas son badge professionnel pendant sa prise de service du matin.', '-15 days',  'qualification', $manager1],
            [$agents['RAT001'], 'incident', 'social',  'moyen',  'Commentaire sur réseaux sociaux', 'Un post sur un réseau social identifié comme émanant de l\'agent contient des critiques envers la direction.', '-50 days',  'archive',   $admin],
            // RAT001 positif
            [$agents['RAT001'], 'positif',  'formulaire', null,  'Aide précieuse à une personne handicapée', 'Un voyageur remercie l\'agent pour l\'aide remarquable apportée à une personne en fauteuil roulant pour accéder au quai.', '-120 days', 'traite',   $manager1],

            // RAT002 Laurent Amina — score ATTENTION : 2 moyen + 1 faible = 2×2+1×1=5
            [$agents['RAT002'], 'incident', 'email',   'moyen',  'Retard de prise de poste', 'L\'agent a pris son poste avec 20 minutes de retard sans justification préalable.', '-5 days',   'nouveau',    $manager2],
            [$agents['RAT002'], 'incident', 'formulaire','moyen', 'Gestion d\'incident problématique', 'Lors d\'une panne, l\'agent n\'a pas appliqué les procédures standard d\'information des voyageurs.', '-35 days',  'qualification', $manager2],
            [$agents['RAT002'], 'incident', 'terrain', 'faible', 'Non-respect consignes tenue', 'L\'agent portait une veste non réglementaire lors de sa prise de service.', '-60 days',  'archive',   $manager2],
            // RAT002 positifs
            [$agents['RAT002'], 'positif',  'email',   null,     'Retour positif voyageur fidèle', 'Un voyageur habituel souligne la constance et la gentillesse de l\'agent au guichet.', '-90 days',  'traite',    $manager2],
            [$agents['RAT002'], 'positif',  'formulaire',null,   'Geste commercial apprécié', 'L\'agent a su désamorcer une situation tendue avec une famille étrangère grâce à son calme et ses explications.', '-45 days',  'traite',    $admin],

            // RAT003 Petit Julien — normal
            [$agents['RAT003'], 'incident', 'terrain', 'faible', 'Oubli de clés en salle de repos', 'Incident mineur : l\'agent a laissé ses clés de service accessibles pendant une pause.', '-200 days', 'archive',   $manager1],
            [$agents['RAT003'], 'positif',  'formulaire',null,   'Félicitations pour intervention rapide', 'L\'agent a évacué efficacement un compartiment suite à une malaise d\'un voyageur.', '-130 days', 'traite',    $admin],

            // RAT004 Garcia Sofia — normal
            [$agents['RAT004'], 'incident', 'email',   'faible', 'Signalement mineur guichet', 'Plainte d\'un voyageur qui estime avoir été mal orienté par l\'agent.', '-180 days', 'traite',    $manager2],
            [$agents['RAT004'], 'positif',  'terrain', null,     'Retour positif direction', 'La direction a été informée d\'un retour exceptionnel d\'un groupe de touristes sur la prise en charge par l\'agent.', '-95 days',  'traite',    $admin],

            // RAT005 Mercier Pierre — normal
            [$agents['RAT005'], 'incident', 'formulaire','faible','Plainte retard explication', 'Voyageur mécontent de la qualité des explications fournies lors d\'un retard.', '-310 days', 'archive',   $manager2],
            [$agents['RAT005'], 'incident', 'email',   'moyen',  'Problème de gestion de foule', 'L\'agent a eu des difficultés à canaliser un flux de voyageurs important lors d\'une perturbation.', '-400 days', 'archive',  $admin],
            [$agents['RAT005'], 'positif',  'formulaire',null,   'Agent du mois – reconnaissance interne', 'Suite à plusieurs retours positifs, la direction propose une reconnaissance interne.', '-30 days',  'nouveau',   $manager1],

            // Quelques signalements récents en statut nouveau pour le dashboard
            [$agents['RAT003'], 'incident', 'social',  'moyen',  'Commentaire négatif observé en ligne', 'Un commentaire attribuable à l\'agent a été détecté sur un forum de voyageurs.', '-2 days',   'nouveau',   $admin],
            [$agents['RAT004'], 'incident', 'formulaire','grave', 'Incident grave à valider', 'Un voyageur rapporte un incident grave impliquant l\'agent lors d\'une vérification de titres.', '-1 days',   'nouveau',   $manager2],
        ];

        foreach ($signalementsData as $idx => $data) {
            [$agent, $type, $canal, $gravite, $titre, $desc, $dateFait, $statut, $createdBy] = $data;
            $s = $mkSig($agent, $type, $canal, $gravite, $titre, $desc, $dateFait, $statut, $createdBy);
            $this->em->persist($s);
            $signalements[] = $s;

            // Historique initial
            $h = new HistoriqueStatut();
            $h->setSignalement($s)->setUser($createdBy)->setAncienStatut(null)->setNouveauStatut('nouveau')->setCommentaire('Signalement créé');
            $this->em->persist($h);

            // Historique supplémentaire si statut avancé
            if ($statut !== 'nouveau') {
                $h2 = new HistoriqueStatut();
                $h2->setSignalement($s)->setUser($rh1)->setAncienStatut('nouveau')->setNouveauStatut('qualification');
                $this->em->persist($h2);
            }
            if (in_array($statut, ['validation', 'traite', 'archive'])) {
                $h3 = new HistoriqueStatut();
                $h3->setSignalement($s)->setUser($rh1)->setAncienStatut('qualification')->setNouveauStatut($statut);
                $this->em->persist($h3);
            }

            $io->writeln("  <info>Créé :</info> [{$type}] {$titre}");
        }

        $this->em->flush();

        $io->section('Création des signalements publics');
        $publicSignalementsData = [
            ['formulaire', 'incident', 'grave', 'Signalement public site', 'Conducteur ligne 13, veste sombre, vers 8h15', 'Incident signalé via le site institutionnel avec une description détaillée du comportement observé.', '-3 days'],
            ['formulaire', 'positif', null, 'Remerciement public site', 'Agent au guichet station Nation', 'Retour positif déposé par un usager pour saluer la qualité de l’accueil et l’aide apportée.', '-7 days'],
            ['social', 'incident', 'moyen', 'Signalement réseaux sociaux', 'Conducteur de bus ligne 183', 'Reprise manuelle d’un signalement provenant des réseaux sociaux.', '-1 day'],
        ];

        foreach ($publicSignalementsData as $index => [$canal, $type, $gravite, $titre, $agentDescription, $desc, $dateFait]) {
            $signalement = new Signalement();
            $signalement->setAgent(null)
                ->setAgentDescription($agentDescription)
                ->setType($type)
                ->setCanal($canal)
                ->setTitre($titre)
                ->setDescription($desc)
                ->setDateFait(new \DateTimeImmutable($dateFait))
                ->setStatut('nouveau')
                ->setCreatedBy($admin);

            if ($type === 'incident' && $gravite !== null) {
                $signalement->setGravite($gravite);
            }

            $this->em->persist($signalement);
            $signalements[] = $signalement;

            $historique = new HistoriqueStatut();
            $historique->setSignalement($signalement)
                ->setUser($admin)
                ->setAncienStatut(null)
                ->setNouveauStatut('nouveau')
                ->setCommentaire('Signalement reçu via ' . ($canal === 'social' ? 'réseaux sociaux' : 'formulaire site'));
            $this->em->persist($historique);

            $this->em->flush();

            if ($index === 0) {
                $piece = $this->createFixtureAttachment($signalement);
                $this->em->persist($piece);
                $this->em->flush();
            }
        }

        $io->section('Création des scénarios de démo hackathon');

        $qrDemo = (new Signalement())
            ->setAgent($agents['RAT001'])
            ->setAgentDescription('Karim Dubois · attribution simulée à 92%')
            ->setType('incident')
            ->setCanal('formulaire')
            ->setTitre('Démo QR + note vocale ligne 72')
            ->setDescription('Signalement issu du QR bus avec contexte prérempli et note vocale simulée.')
            ->setTranslatedDescription('[Traduction simulée depuis Anglais] Driver closed the doors too early while passengers were still stepping out.')
            ->setVoiceTranscript('Transcription simulée : Driver closed the doors too early while passengers were still stepping out.')
            ->setSourceLanguage('en')
            ->setSourceLine('72')
            ->setSourceVehicle('4589')
            ->setSourceStop('Nation')
            ->setSourceEntryMode('qr_scan_sim')
            ->setDateFait(new \DateTimeImmutable('-2 hours'))
            ->setGravite('grave')
            ->setStatut('qualification')
            ->setCreatedBy($admin);
        $this->em->persist($qrDemo);

        $hQr1 = (new HistoriqueStatut())
            ->setSignalement($qrDemo)
            ->setUser($admin)
            ->setAncienStatut(null)
            ->setNouveauStatut('nouveau')
            ->setCommentaire('Signalement reçu via QR simulé.');
        $this->em->persist($hQr1);

        $hQr2 = (new HistoriqueStatut())
            ->setSignalement($qrDemo)
            ->setUser($admin)
            ->setAncienStatut('nouveau')
            ->setNouveauStatut('qualification')
            ->setCommentaire('Attribution agent simulée depuis planning temps réel.');
        $this->em->persist($hQr2);

        $socialDemo = (new Signalement())
            ->setAgent($agents['RAT004'])
            ->setAgentDescription('Sofia Garcia · attribution simulée à 68%')
            ->setType('incident')
            ->setCanal('social')
            ->setTitre('Démo inbox sociale Instagram')
            ->setDescription('Driver ignored accessibility request on line 91 and left the stop too fast.')
            ->setTranslatedDescription('[Traduction simulée depuis Anglais] Le conducteur a ignoré une demande d’accessibilité sur la ligne 91 et est reparti trop vite.')
            ->setSourceLanguage('en')
            ->setSourcePlatform('Instagram')
            ->setSourceLine('91')
            ->setSourceVehicle('9134')
            ->setSourceStop('Montparnasse')
            ->setSourceEntryMode('social_connector_sim')
            ->setDateFait(new \DateTimeImmutable('-5 hours'))
            ->setGravite('moyen')
            ->setStatut('qualification')
            ->setCreatedBy($admin);
        $this->em->persist($socialDemo);

        $hSocial = (new HistoriqueStatut())
            ->setSignalement($socialDemo)
            ->setUser($admin)
            ->setAncienStatut(null)
            ->setNouveauStatut('qualification')
            ->setCommentaire('Import simulé depuis l’inbox sociale.');
        $this->em->persist($hSocial);

        $this->em->flush();

        // === COMMENTAIRES ===
        $io->section('Ajout de commentaires');
        // Sur le 1er signalement (RAT001, altercation)
        if (isset($signalements[0])) {
            $c = new CommentaireSignalement();
            $c->setSignalement($signalements[0])->setUser($rh1)->setContenu('Dossier en cours d\'instruction. Convocation prévue la semaine prochaine.');
            $this->em->persist($c);

            $c2 = new CommentaireSignalement();
            $c2->setSignalement($signalements[0])->setUser($manager1)->setContenu('L\'agent a fourni sa version des faits. Elle diffère sensiblement du témoignage.');
            $this->em->persist($c2);
        }

        // Sur un positif
        if (isset($signalements[5])) {
            $c = new CommentaireSignalement();
            $c->setSignalement($signalements[5])->setUser($admin)->setContenu('À intégrer dans le dossier de valorisation de l\'agent. Bravo !');
            $this->em->persist($c);
        }

        $this->em->flush();

        // === COURRIER SUR UN SIGNALEMENT ===
        $io->section('Génération d\'un exemple de courrier');
        if (isset($signalements[2])) { // Traité
            $s = $signalements[2];
            $agent = $s->getAgent();
            $contenu = <<<EOT
RATP — Service Ressources Humaines
Date : {$now->format('d/m/Y')}

Objet : Notification concernant l'agent {$agent->getPrenom()} {$agent->getNom()} (matricule {$agent->getMatricule()})

Madame, Monsieur,

Nous vous informons qu'un signalement de type « incident » a été enregistré
le {$s->getDateFait()->format('d/m/Y')} concernant l'agent {$agent->getPrenom()} {$agent->getNom()},
matricule {$agent->getMatricule()}, affecté au centre {$agent->getCentre()}.

Faits rapportés :
{$s->getDescription()}

---
Gravité : {$s->getGravite()}
Canal de réception : {$s->getCanalLabel()}
Statut actuel du dossier : Traité
---

Ce courrier vous est adressé afin de vous informer des suites données à ce signalement.

Service Ressources Humaines — RATP
EOT;

            $courrier = new CourrierDraft();
            $courrier->setSignalement($s)->setContenu($contenu)->setStatut('valide')->setValidatedBy($rh1);
            $courrier->setDispatchReference('MLV-FIXTURE-0001')
                ->setDispatchStatus('distribue')
                ->setDispatchedAt(new \DateTime('-2 days'))
                ->setLastDispatchUpdateAt(new \DateTime('-1 day'))
                ->setDispatchJournal([
                    ['status' => 'cree', 'label' => 'Créé chez Maileva', 'message' => 'Courrier pris en charge par le connecteur simulé.', 'at' => (new \DateTimeImmutable('-2 days'))->format(\DateTimeInterface::ATOM)],
                    ['status' => 'envoye', 'label' => 'Envoyé', 'message' => 'Courrier marqué comme expédié au prestataire.', 'at' => (new \DateTimeImmutable('-2 days +1 hour'))->format(\DateTimeInterface::ATOM)],
                    ['status' => 'distribue', 'label' => 'Distribué / mis à disposition', 'message' => 'Le courrier est déclaré distribué / mis à disposition par le connecteur simulé.', 'at' => (new \DateTimeImmutable('-1 day'))->format(\DateTimeInterface::ATOM)],
                ]);
            $this->em->persist($courrier);
            $this->em->flush();
            $io->writeln('  <info>Courrier validé créé pour :</info> ' . $s->getTitre());
        }

        $io->success('Fixtures chargées avec succès !');
        $io->table(
            ['Ressource', 'Nombre'],
            [
                ['Utilisateurs', count($usersData)],
                ['Agents', count($agentsData)],
                ['Signalements', count($signalementsData) + count($publicSignalementsData)],
                ['Commentaires', 3],
                ['Courriers', 1],
            ]
        );

        $io->note('Connexion admin : admin@ratp.fr / Admin1234!');
        $io->note('Connexion admin développeur : devadmin@ratp.fr / DevAdmin1!');
        $io->note('Connexion manager : manager1@ratp.fr / Manager1!');
        $io->note('Connexion RH : rh1@ratp.fr / Rh12345!');

        return Command::SUCCESS;
    }

    private function clearUploadDirectory(): void
    {
        if (!is_dir($this->uploadDir)) {
            return;
        }

        foreach (glob($this->uploadDir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    private function createFixtureAttachment(Signalement $signalement): PieceJointe
    {
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0775, true);
        }

        $filename = sprintf('fixture-public-%d.pdf', $signalement->getId());
        $filepath = $this->uploadDir . '/' . $filename;
        file_put_contents($filepath, "Fixture PJ pour le signalement public #{$signalement->getId()}\n");

        $piece = new PieceJointe();
        $piece->setSignalement($signalement)
            ->setFilename($filename)
            ->setOriginalName('signalement-public.pdf')
            ->setMimeType('application/pdf')
            ->setSize(filesize($filepath) ?: 0)
            ->setVisibility(PieceJointe::VISIBILITY_PUBLIC)
            ->setCategory('public_submission')
            ->setUploadedBy(null);

        return $piece;
    }
}

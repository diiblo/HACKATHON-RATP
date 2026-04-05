<?php

namespace App\Controller;

use App\Entity\HistoriqueStatut;
use App\Entity\Signalement;
use App\Form\PublicSignalementType;
use App\Service\AgentPlanningSimulator;
use App\Service\AiPreTriageService;
use App\Service\AiVoiceProcessingService;
use App\Service\AuditLogger;
use App\Service\PieceJointeService;
use App\Service\QrAccessSimulator;
use App\Service\VoiceNoteSimulationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/public')]
class PublicSignalementController extends AbstractController
{
    #[Route('/acces-rapide', name: 'app_public_quick_access', methods: ['GET'])]
    public function quickAccess(QrAccessSimulator $qrAccessSimulator): Response
    {
        return $this->render('public/quick_access.html.twig', [
            'entries' => $qrAccessSimulator->getDemoEntries(),
        ]);
    }

    #[Route('/scan/{code}', name: 'app_public_scan_qr', methods: ['GET'])]
    public function scanQr(string $code, QrAccessSimulator $qrAccessSimulator): Response
    {
        $entry = $qrAccessSimulator->decode($code);
        if ($entry === null) {
            $this->addFlash('warning', 'QR / lien terrain inconnu dans la simulation.');
            return $this->redirectToRoute('app_public_quick_access');
        }

        return $this->redirectToRoute('app_public_signalement_terrain', [
            'line' => $entry['line'],
            'vehicle' => $entry['vehicle'],
            'stop' => $entry['stop'],
            'occurredAt' => (new \DateTimeImmutable())->format(\DateTimeInterface::ATOM),
            'entryMode' => 'qr_scan_sim',
        ]);
    }

    #[Route('/signalement', name: 'app_public_signalement_site', methods: ['GET', 'POST'])]
    public function signalementSite(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AgentPlanningSimulator $agentPlanningSimulator,
        AiVoiceProcessingService $aiVoiceProcessingService,
        VoiceNoteSimulationService $voiceNoteSimulationService,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService,
        #[Autowire(service: 'limiter.public_signalement')] RateLimiterFactory $rateLimiter
    ): Response {
        return $this->handlePublicForm($request, $em, $pieceJointeService, $agentPlanningSimulator, $aiVoiceProcessingService, $voiceNoteSimulationService, $auditLogger, $aiPreTriageService, 'formulaire', 'public/signalement_site.html.twig', $rateLimiter);
    }

    #[Route('/signalement/terrain', name: 'app_public_signalement_terrain', methods: ['GET', 'POST'])]
    public function signalementTerrain(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AgentPlanningSimulator $agentPlanningSimulator,
        AiVoiceProcessingService $aiVoiceProcessingService,
        VoiceNoteSimulationService $voiceNoteSimulationService,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService,
        #[Autowire(service: 'limiter.public_signalement')] RateLimiterFactory $rateLimiter
    ): Response {
        return $this->handlePublicForm($request, $em, $pieceJointeService, $agentPlanningSimulator, $aiVoiceProcessingService, $voiceNoteSimulationService, $auditLogger, $aiPreTriageService, 'terrain', 'public/signalement_terrain.html.twig', $rateLimiter);
    }

    #[Route('/signalement/message-direct', name: 'app_public_signalement_dm', methods: ['GET', 'POST'])]
    public function signalementDm(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AgentPlanningSimulator $agentPlanningSimulator,
        AiVoiceProcessingService $aiVoiceProcessingService,
        VoiceNoteSimulationService $voiceNoteSimulationService,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService,
        #[Autowire(service: 'limiter.public_signalement')] RateLimiterFactory $rateLimiter
    ): Response {
        return $this->handlePublicForm($request, $em, $pieceJointeService, $agentPlanningSimulator, $aiVoiceProcessingService, $voiceNoteSimulationService, $auditLogger, $aiPreTriageService, 'dm', 'public/signalement_dm.html.twig', $rateLimiter);
    }

    #[Route('/signalement/reseaux-sociaux', name: 'app_public_signalement_rs', methods: ['GET', 'POST'])]
    public function signalementRS(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AgentPlanningSimulator $agentPlanningSimulator,
        AiVoiceProcessingService $aiVoiceProcessingService,
        VoiceNoteSimulationService $voiceNoteSimulationService,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService,
        #[Autowire(service: 'limiter.public_signalement')] RateLimiterFactory $rateLimiter
    ): Response {
        return $this->handlePublicForm($request, $em, $pieceJointeService, $agentPlanningSimulator, $aiVoiceProcessingService, $voiceNoteSimulationService, $auditLogger, $aiPreTriageService, 'social', 'public/signalement_rs.html.twig', $rateLimiter);
    }

    #[Route('/confirmation', name: 'app_public_confirmation', methods: ['GET'])]
    public function confirmation(Request $request): Response
    {
        return $this->render('public/confirmation.html.twig', [
            'reference' => (string) $request->query->get('reference', ''),
        ]);
    }

    private function handlePublicForm(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AgentPlanningSimulator $agentPlanningSimulator,
        AiVoiceProcessingService $aiVoiceProcessingService,
        VoiceNoteSimulationService $voiceNoteSimulationService,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService,
        string $canal,
        string $template,
        RateLimiterFactory $rateLimiterFactory
    ): Response {
        $signalement = new Signalement();
        $prefillContext = $this->buildPrefillContext($request);
        $signalement->setCanal($canal);
        $signalement->setStatut('nouveau');
        $signalement->setDateFait(new \DateTimeImmutable());
        $this->applyPrefillContext($signalement, $prefillContext, $canal);

        $form = $this->createForm(PublicSignalementType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $limiter = $rateLimiterFactory->create($request->getClientIp() ?? 'unknown');
            if (!$limiter->consume(1)->isAccepted()) {
                $this->addFlash('error', 'Trop de soumissions. Merci de réessayer dans une heure.');
                return $this->render($template, ['form' => $form, 'prefillContext' => $prefillContext]);
            }

            // Canal forcé selon la route
            $signalement->setCanal($canal);
            $signalement->setTitre($this->buildPublicTitle($signalement, $canal));

            // Forcer gravite=null si type positif
            if ($signalement->getType() === 'positif') {
                $signalement->setGravite(null);
            }

            // Agent null (formulaire public)
            $signalement->setAgent(null);

            // Pas d'utilisateur connecté pour les formulaires publics
            // On utilise le premier admin comme createdBy (ou on crée un user système)
            // Pour le MVP : l'admin est automatiquement affecté comme créateur technique
            $adminUser = $em->getRepository(\App\Entity\User::class)->findOneBy(['email' => 'admin@ratp.fr']);
            if ($adminUser) {
                $signalement->setCreatedBy($adminUser);
            } else {
                // Fallback : prend le premier utilisateur disponible
                $anyUser = $em->getRepository(\App\Entity\User::class)->findOneBy([]);
                if (!$anyUser) {
                    throw new \RuntimeException('Aucun utilisateur système disponible.');
                }
                $signalement->setCreatedBy($anyUser);
            }

            $planning = $agentPlanningSimulator->resolve(
                $signalement->getSourceLine(),
                $signalement->getSourceVehicle(),
                $signalement->getDateFait()
            );
            if ($planning !== null) {
                $signalement->setAgent($planning['agent']);
                // Ne pas écraser la description d'agent tapée par l'utilisateur
                if (trim((string) $signalement->getAgentDescription()) === '' || $signalement->getAgentDescription() === 'Conducteur de la ligne ' . $signalement->getSourceLine()) {
                    $signalement->setAgentDescription($planning['agent']->getFullName() . ' · attribution automatique à ' . $planning['confidence'] . '%');
                }
            }

            $em->persist($signalement);

            // Historique initial
            $h = new HistoriqueStatut();
            $h->setSignalement($signalement)
              ->setUser($signalement->getCreatedBy())
              ->setAncienStatut(null)
              ->setNouveauStatut('nouveau')
              ->setCommentaire('Signalement reçu via ' . match ($canal) {
                  'social'  => 'réseaux sociaux',
                  'terrain' => 'accès QR terrain',
                  'dm'      => 'message direct (lien partagé)',
                  default   => 'formulaire web',
              });
            $em->persist($h);

            if ($planning !== null) {
                $assignment = new HistoriqueStatut();
                $assignment->setSignalement($signalement)
                    ->setUser($signalement->getCreatedBy())
                    ->setAncienStatut('nouveau')
                    ->setNouveauStatut('qualification')
                    ->setCommentaire($planning['reason']);
                $em->persist($assignment);
                $signalement->setStatut('qualification');
            }

            // Pièce jointe si fournie
            $fichier = $form->get('pieceJointe')->getData();
            if ($fichier) {
                try {
                    $piece = $pieceJointeService->handleUpload($fichier, $signalement, null, \App\Entity\PieceJointe::VISIBILITY_INTERNAL, 'public_submission');
                    $em->persist($piece);
                } catch (\RuntimeException $e) {
                    $this->addFlash('warning', 'Fichier non joint : ' . $e->getMessage());
                }
            }

            $noteVocale = $form->get('noteVocale')->getData();
            if ($noteVocale) {
                try {
                    $usedFallback = false;
                    try {
                        $voiceData = $aiVoiceProcessingService->process(
                            $noteVocale,
                            $form->get('voiceLanguageHint')->getData()
                        );
                    } catch (\Throwable $voiceException) {
                        $voiceData = $voiceNoteSimulationService->process(
                            $noteVocale,
                            $signalement,
                            $form->get('voiceLanguageHint')->getData()
                        );
                        $voiceData['provider'] = 'Simulation locale';
                        $usedFallback = true;
                        $auditLogger->log(
                            'voice.processing.fallback',
                            sprintf('Bascule sur le traitement vocal simulé pour le signalement #%d.', $signalement->getId() ?? 0),
                            ['error' => $voiceException->getMessage()],
                            $signalement,
                            null,
                            'public'
                        );
                    }
                    $signalement->setSourceLanguage($voiceData['language']);
                    $signalement->setVoiceTranscript($voiceData['transcript']);
                    $signalement->setTranslatedDescription($voiceData['translation']);

                    $piece = $pieceJointeService->handleUpload($noteVocale, $signalement, null, \App\Entity\PieceJointe::VISIBILITY_RESTRICTED, 'voice_note');
                    $em->persist($piece);
                    $auditLogger->log(
                        'voice.processing.completed',
                        sprintf('Traitement vocal %s pour le signalement #%d.', $usedFallback ? 'de repli' : 'IA réel', $signalement->getId() ?? 0),
                        ['provider' => $voiceData['provider'] ?? null, 'language' => $voiceData['language'] ?? null],
                        $signalement,
                        null,
                        'public'
                    );
                } catch (\RuntimeException $e) {
                    $this->addFlash('warning', 'Note vocale non jointe : ' . $e->getMessage());
                }
            }

            $em->flush();
            $aiPreTriageService->preTriage($signalement);
            $em->flush();
            $auditLogger->log(
                'public.signalement.submitted',
                sprintf('Signalement public #%d soumis via %s.', $signalement->getId(), $canal),
                [
                    'entryMode' => $signalement->getSourceEntryMode(),
                    'line' => $signalement->getSourceLine(),
                    'vehicle' => $signalement->getSourceVehicle(),
                    'hasVoiceNote' => $signalement->getVoiceTranscript() !== null,
                    'hasPlanningMatch' => $signalement->getAgent() !== null,
                ],
                $signalement,
                null,
                'public'
            );

            return $this->redirectToRoute('app_public_confirmation', [
                'reference' => $this->buildPublicReference($signalement),
            ]);
        }

        return $this->render($template, [
            'form' => $form,
            'prefillContext' => $prefillContext,
        ]);
    }

    private function buildPrefillContext(Request $request): array
    {
        $line = trim((string) $request->query->get('line', ''));
        $vehicle = trim((string) $request->query->get('vehicle', ''));
        $stop = trim((string) $request->query->get('stop', ''));
        $occurredAtRaw = trim((string) $request->query->get('occurredAt', ''));
        $occurredAt = null;

        if ($occurredAtRaw !== '') {
            try {
                $occurredAt = new \DateTimeImmutable($occurredAtRaw);
            } catch (\Throwable) {
                $occurredAt = null;
            }
        }

        return [
            'line' => $line,
            'vehicle' => $vehicle,
            'stop' => $stop,
            'entryMode' => trim((string) $request->query->get('entryMode', '')),
            'occurredAt' => $occurredAt,
            'platform' => trim((string) $request->query->get('platform', '')),
            'hasContext' => $line !== '' || $vehicle !== '' || $stop !== '' || $occurredAt !== null,
        ];
    }

    private function applyPrefillContext(Signalement $signalement, array $prefillContext, string $canal): void
    {
        if (!$prefillContext['hasContext']) {
            return;
        }

        $signalement->setSourceLine($prefillContext['line'] ?: null);
        $signalement->setSourceVehicle($prefillContext['vehicle'] ?: null);
        $signalement->setSourceStop($prefillContext['stop'] ?: null);
        $signalement->setSourceEntryMode($prefillContext['entryMode'] !== '' ? $prefillContext['entryMode'] : 'public_link');

        $parts = [];
        if ($prefillContext['line'] !== '') {
            $parts[] = 'ligne ' . $prefillContext['line'];
        }
        if ($prefillContext['vehicle'] !== '') {
            $parts[] = 'véhicule ' . $prefillContext['vehicle'];
        }
        if ($prefillContext['stop'] !== '') {
            $parts[] = 'arrêt ' . $prefillContext['stop'];
        }

        $contextSummary = $parts !== [] ? implode(', ', $parts) : 'contexte véhicule';
        if ($prefillContext['occurredAt'] instanceof \DateTimeImmutable) {
            $signalement->setDateFait($prefillContext['occurredAt']);
        }

        $signalement->setTitre(($canal === 'social' ? 'Signalement social' : 'Signalement bus') . ' — ' . $contextSummary);

        if ($prefillContext['line'] !== '') {
            $signalement->setAgentDescription('Conducteur de la ligne ' . $prefillContext['line']);
        }
    }

    private function buildPublicTitle(Signalement $signalement, string $canal): string
    {
        $prefix = $signalement->getType() === 'positif' ? 'Retour positif' : 'Incident';
        $source = match ($canal) {
            'social'  => 'canal social',
            'terrain' => 'terrain',
            'dm'      => 'message direct',
            default   => 'formulaire web',
        };

        $summary = trim((string) $signalement->getDescription());
        $summary = preg_replace('/\s+/u', ' ', $summary ?? '') ?? '';
        if ($summary !== '') {
            $summary = mb_strimwidth($summary, 0, 72, '...');
            return sprintf('%s - %s', $prefix, $summary);
        }

        return sprintf('%s - %s', $prefix, $source);
    }

    private function buildPublicReference(Signalement $signalement): string
    {
        return sprintf('SIG-%s-%04d', $signalement->getCreatedAt()->format('Ymd'), $signalement->getId());
    }
}

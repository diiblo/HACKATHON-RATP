<?php

namespace App\Controller;

use App\Repository\AgentRepository;
use App\Repository\AuditLogRepository;
use App\Repository\CourrierDraftRepository;
use App\Repository\SignalementRepository;
use App\Service\AiTrendAnalyzerService;
use App\Service\ScoreCalculator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class DashboardController extends AbstractController
{
    #[Route('/dashboard/live', name: 'app_dashboard_live', methods: ['GET'])]
    public function live(
        SignalementRepository $signalementRepo,
        AgentRepository $agentRepo,
        ScoreCalculator $scoreCalc
    ): JsonResponse {
        $stats = $signalementRepo->countByStatut();
        $since = new \DateTimeImmutable('-5 minutes');
        $recentlyAdded = $signalementRepo->countCreatedSince($since);

        $agentsWithScores = array_map(
            fn(array $item) => [
                'agent' => $item['agent'],
                'rawScore' => $item['score'],
                'level' => $scoreCalc->mapToLevel($item['score']),
            ],
            $agentRepo->findAllWithScores()
        );
        $critiques = array_values(array_filter($agentsWithScores, fn($i) => $i['level'] === 4));
        $alertes   = array_values(array_filter($agentsWithScores, fn($i) => $i['level'] === 3));

        return $this->json([
            'stats' => $stats,
            'recentlyAdded' => $recentlyAdded,
            'critiques' => array_map(fn($i) => [
                'id'    => $i['agent']->getId(),
                'nom'   => $i['agent']->getFullName(),
                'level' => $i['level'],
                'score' => $i['rawScore'],
            ], $critiques),
            'alertes' => array_map(fn($i) => [
                'id'    => $i['agent']->getId(),
                'nom'   => $i['agent']->getFullName(),
                'level' => $i['level'],
                'score' => $i['rawScore'],
            ], $alertes),
        ]);
    }

    #[Route('/dashboard/analyser-tendances', name: 'app_dashboard_analyser_tendances', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function analyserTendances(
        Request $request,
        AiTrendAnalyzerService $analyzer
    ): Response {
        if (!$this->isCsrfTokenValid('trend-analysis', $request->request->get('_token'))) {
            throw $this->createAccessDeniedException();
        }

        try {
            $analysis = $analyzer->analyze($this->getUser());
            return $this->render('dashboard/_trend_analysis.html.twig', ['analysis' => $analysis]);
        } catch (\Throwable $e) {
            return $this->render('dashboard/_trend_analysis.html.twig', [
                'analysis' => null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    #[Route('/', name: 'app_dashboard')]
    public function index(
        SignalementRepository $signalementRepo,
        AgentRepository $agentRepo,
        ScoreCalculator $scoreCalc,
        AuditLogRepository $auditLogRepository,
        CourrierDraftRepository $courrierDraftRepository
    ): Response {
        $stats = $signalementRepo->countByStatut();
        $typeStats = $signalementRepo->countByType();
        $graviteStats = $signalementRepo->countByGravite();
        $recentSignalements = $signalementRepo->findRecentForDashboard(10);
        $agentsWithScores = array_map(
            fn(array $item) => [
                'agent' => $item['agent'],
                'rawScore' => $item['score'],
                'level' => $scoreCalc->mapToLevel($item['score']),
            ],
            $agentRepo->findAllWithScores()
        );

        $agentsCritiques = array_filter($agentsWithScores, fn(array $item) => $item['level'] === 4);
        $agentsEnAlerte = array_filter($agentsWithScores, fn(array $item) => $item['level'] === 3);
        $videoUrgentSignalements = array_values(array_filter(
            $recentSignalements,
            static fn($signalement) => $signalement->isIncident() && !$signalement->hasComplaintProof() && !$signalement->isVideoEvidenceExpired()
        ));
        $aiStats = [
            'preTriageCount' => $auditLogRepository->countByActionPrefix('signalement.ai.pretriage'),
            'failoverCount' => $auditLogRepository->countByActionPrefix('ai.failover.used'),
            'analysisCount' => $auditLogRepository->countByActionPrefix('signalement.ai.'),
            'recentEvents' => $auditLogRepository->findLatestByActionPrefixes(['signalement.ai.', 'ai.failover.'], 6),
        ];
        $courrierStats = [
            'enValidation' => $courrierDraftRepository->countByStatus('en_validation'),
            'valide' => $courrierDraftRepository->countByStatus('valide'),
        ];

        return $this->render('dashboard/index.html.twig', [
            'stats'              => $stats,
            'recentSignalements' => $recentSignalements,
            'agentsCritiques'    => array_values($agentsCritiques),
            'agentsEnAlerte'     => array_values($agentsEnAlerte),
            'scoreCalc'          => $scoreCalc,
            'typeStats'          => $typeStats,
            'graviteStats'       => $graviteStats,
            'videoUrgentSignalements' => $videoUrgentSignalements,
            'aiStats'            => $aiStats,
            'courrierStats'      => $courrierStats,
        ]);
    }
}

<?php

namespace App\Controller;

use App\Entity\HistoriqueStatut;
use App\Entity\Signalement;
use App\Form\CommentaireType;
use App\Form\SignalementType;
use App\Repository\SignalementRepository;
use App\Service\AiConfigurationManager;
use App\Service\AiSignalementAnalyzer;
use App\Service\AuditLogger;
use App\Service\PieceJointeService;
use App\Service\ScoreCalculator;
use App\Service\SignalementNotificationService;
use App\Service\StatutWorkflow;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/signalement')]
#[IsGranted('ROLE_USER')]
class SignalementController extends AbstractController
{
    #[Route('/', name: 'app_signalement_index', methods: ['GET'])]
    public function index(Request $request, SignalementRepository $repo): Response
    {
        $page = max(1, $request->query->getInt('page', 1));
        $filters = array_filter([
            'q'       => $request->query->get('q'),
            'statut'  => $request->query->get('statut'),
            'type'    => $request->query->get('type'),
            'gravite' => $request->query->get('gravite'),
            'agent'   => $request->query->get('agent'),
            'centre'  => $request->query->get('centre'),
            'date_from' => $request->query->get('date_from'),
            'date_to' => $request->query->get('date_to'),
        ]);

        $pagination = $repo->paginateFilteredList($filters, $page, 10);

        return $this->render('signalement/index.html.twig', [
            'signalements' => $pagination['items'],
            'filters'      => $filters,
            'statuts'      => Signalement::STATUT_LABELS,
            'types'        => ['incident' => 'Incident', 'positif' => 'Avis positif'],
            'gravites'     => ['faible' => 'Faible', 'moyen' => 'Moyen', 'grave' => 'Grave'],
            'pagination'   => $pagination,
            'queryParams'  => $filters,
        ]);
    }

    #[Route('/new', name: 'app_signalement_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService
    ): Response
    {
        $signalement = new Signalement();
        $form = $this->createForm(SignalementType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Forcer gravite=null si type positif
            if ($signalement->getType() === 'positif') {
                $signalement->setGravite(null);
            }

            $signalement->setCreatedBy($this->getUser());
            $em->persist($signalement);

            // Historique initial
            $historique = new HistoriqueStatut();
            $historique->setSignalement($signalement);
            $historique->setUser($this->getUser());
            $historique->setAncienStatut(null);
            $historique->setNouveauStatut('nouveau');
            $historique->setCommentaire('Signalement créé');
            $em->persist($historique);

            $this->handlePieceJointeUpload($form, $signalement, $pieceJointeService, $em);
            $em->flush();

            $this->addFlash('success', 'Signalement enregistré avec succès.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        return $this->render('signalement/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_signalement_show', methods: ['GET', 'POST'])]
    public function show(
        Signalement $signalement,
        Request $request,
        EntityManagerInterface $em,
        StatutWorkflow $workflow,
        ScoreCalculator $scoreCalc,
        AiConfigurationManager $aiConfigurationManager
    ): Response {
        $commentForm = $this->createForm(CommentaireType::class);

        $allowedTransitions = $workflow->getAllowedTransitions($signalement->getStatut());
        $score = $signalement->getAgent() !== null
            ? $scoreCalc->calculate($signalement->getAgent())
            : null;
        $hasAiConfig = $aiConfigurationManager->getDefaultActiveConfig() !== null;
        $preTriageComment = null;
        foreach ($signalement->getCommentaires() as $commentaire) {
            if (str_starts_with($commentaire->getContenu(), 'Pre-tri IA automatique')) {
                $preTriageComment = $commentaire;
                break;
            }
        }

        return $this->render('signalement/show.html.twig', [
            'signalement'        => $signalement,
            'commentForm'        => $commentForm,
            'allowedTransitions' => $allowedTransitions,
            'score'              => $score,
            'scoreCalc'          => $scoreCalc,
            'workflow'           => $workflow,
            'hasAiConfig'        => $hasAiConfig,
            'preTriageComment'   => $preTriageComment,
        ]);
    }

    #[Route('/{id}/ai-analysis', name: 'app_signalement_ai_analysis', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MANAGER')]
    public function aiAnalysis(
        Signalement $signalement,
        Request $request,
        AiSignalementAnalyzer $analyzer,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('ai-analysis-' . $signalement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $analysis = $analyzer->analyze($signalement, $this->getUser());
        } catch (\RuntimeException $e) {
            $this->addFlash('warning', $e->getMessage());
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Analyse IA indisponible : ' . $e->getMessage());
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        $auditLogger->log(
            'signalement.ai.analyzed',
            sprintf('Analyse IA exécutée sur le signalement #%d.', $signalement->getId()),
            ['provider' => $analysis['provider'] ?? null],
            $signalement,
            $this->getUser()
        );

        return $this->render('signalement/ai_analysis.html.twig', [
            'signalement' => $signalement,
            'analysis' => $analysis,
        ]);
    }

    #[Route('/{id}/ai-alert', name: 'app_signalement_ai_alert', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MANAGER')]
    public function aiAlert(
        Signalement $signalement,
        Request $request,
        AiSignalementAnalyzer $analyzer,
        SignalementNotificationService $notificationService,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('ai-alert-' . $signalement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        try {
            $analysis = $analyzer->analyze($signalement, $this->getUser());
            /** @var \App\Entity\User $user */
            $user = $this->getUser();
            $notificationService->sendAiDecisionAlert($signalement, $analysis, $user);
            $auditLogger->log(
                'signalement.ai.alert_sent',
                sprintf('Alerte décisionnelle IA envoyée pour le signalement #%d.', $signalement->getId()),
                [
                    'subject' => $analysis['alertEmailSubject'] ?? null,
                    'roles' => $analysis['alertTargetRoles'] ?? [],
                ],
                $signalement,
                $user
            );
            $this->addFlash('success', 'Alerte décisionnelle IA envoyée aux responsables ciblés.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Impossible d’envoyer l’alerte IA : ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
    }

    #[Route('/{id}/complaint-proof', name: 'app_signalement_complaint_proof', methods: ['POST'])]
    public function complaintProof(
        Signalement $signalement,
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('complaint-proof-' . $signalement->getId(), (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $proof = $request->files->get('plainte_proof');
        if ($proof === null) {
            $this->addFlash('error', 'Une preuve de dépôt de plainte est requise.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        try {
            $piece = $pieceJointeService->handleUpload($proof, $signalement, $this->getUser(), \App\Entity\PieceJointe::VISIBILITY_RESTRICTED, 'complaint_proof');
            $em->persist($piece);
        } catch (\RuntimeException $e) {
            $this->addFlash('error', $e->getMessage());
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        $signalement->setPlainteDeposeeAt(new \DateTime());
        $signalement->setPlainteCommentaire(trim((string) $request->request->get('plainte_commentaire', '')) ?: null);

        $historique = (new HistoriqueStatut())
            ->setSignalement($signalement)
            ->setUser($this->getUser())
            ->setAncienStatut($signalement->getStatut())
            ->setNouveauStatut($signalement->getStatut())
            ->setCommentaire('Preuve de dépôt de plainte ajoutée pour conservation des preuves vidéo.');
        $em->persist($historique);
        $auditLogger->log(
            'signalement.complaint_proof.added',
            sprintf('Preuve de plainte ajoutée au signalement #%d.', $signalement->getId()),
            ['comment' => $signalement->getPlainteCommentaire()],
            $signalement,
            $this->getUser()
        );
        $em->flush();

        $this->addFlash('success', 'Dépôt de plainte enregistré. Le timer vidéo est sécurisé dans la simulation.');
        return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
    }

    #[Route('/{id}/edit', name: 'app_signalement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Signalement $signalement,
        Request $request,
        EntityManagerInterface $em,
        PieceJointeService $pieceJointeService
    ): Response
    {
        // Seuls les signalements en statut nouveau ou qualification sont éditables (sauf admin)
        if (!in_array($signalement->getStatut(), ['nouveau', 'qualification'])
            && !$this->isGranted('ROLE_ADMIN')) {
            $this->addFlash('error', 'Ce signalement ne peut plus être modifié à ce stade.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        $form = $this->createForm(SignalementType::class, $signalement);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($signalement->getType() === 'positif') {
                $signalement->setGravite(null);
            }

            $this->handlePieceJointeUpload($form, $signalement, $pieceJointeService, $em);
            $em->flush();
            $this->addFlash('success', 'Signalement modifié.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        return $this->render('signalement/edit.html.twig', [
            'form'        => $form,
            'signalement' => $signalement,
        ]);
    }

    #[Route('/{id}/transition/{statut}', name: 'app_signalement_transition', methods: ['POST'])]
    public function transition(
        Signalement $signalement,
        string $statut,
        Request $request,
        EntityManagerInterface $em,
        StatutWorkflow $workflow,
        SignalementNotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('transition-' . $signalement->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Token invalide.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        if (!$workflow->canTransition($signalement->getStatut(), $statut)) {
            $this->addFlash('error', 'Transition non autorisée.');
            return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
        }

        $ancienStatut = $signalement->getStatut();
        $signalement->setStatut($statut);

        $historique = new HistoriqueStatut();
        $historique->setSignalement($signalement);
        $historique->setUser($this->getUser());
        $historique->setAncienStatut($ancienStatut);
        $historique->setNouveauStatut($statut);
        $commentaire = $request->request->get('commentaire_transition');
        if ($commentaire) {
            $historique->setCommentaire($commentaire);
        }
        $em->persist($historique);
        $em->flush();
        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $notificationService->sendStatusTransitionNotification($signalement, $ancienStatut, $statut, $user);

        $label = Signalement::STATUT_LABELS[$statut] ?? $statut;
        $this->addFlash('success', 'Statut mis à jour : ' . $label . '.');

        return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
    }

    #[Route('/{id}/delete', name: 'app_signalement_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Signalement $signalement, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-signalement-' . $signalement->getId(), $request->request->get('_token'))) {
            $em->remove($signalement);
            $em->flush();
            $this->addFlash('success', 'Signalement supprimé.');
        }

        return $this->redirectToRoute('app_signalement_index');
    }

    private function handlePieceJointeUpload(
        mixed $form,
        Signalement $signalement,
        PieceJointeService $pieceJointeService,
        EntityManagerInterface $em
    ): void {
        $fichier = $form->get('pieceJointe')->getData();
        if ($fichier === null) {
            return;
        }

        try {
            $piece = $pieceJointeService->handleUpload($fichier, $signalement, $this->getUser(), \App\Entity\PieceJointe::VISIBILITY_INTERNAL, 'internal_attachment');
            $em->persist($piece);
        } catch (\RuntimeException $e) {
            $this->addFlash('warning', 'Fichier non joint : ' . $e->getMessage());
        }
    }
}

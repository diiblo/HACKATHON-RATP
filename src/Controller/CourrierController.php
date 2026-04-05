<?php

namespace App\Controller;

use App\Entity\CourrierDraft;
use App\Entity\Signalement;
use App\Form\CourrierDraftType;
use App\Repository\CourrierDraftRepository;
use App\Service\AiConfigurationManager;
use App\Service\AiSignalementAnalyzer;
use App\Service\MailevaMockClient;
use App\Service\PdfGenerator;
use App\Service\AuditLogger;
use App\Service\SignalementNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/courrier')]
#[IsGranted('ROLE_USER')]
class CourrierController extends AbstractController
{
    #[Route('/rh', name: 'app_courrier_index', methods: ['GET'])]
    #[IsGranted('ROLE_RH')]
    public function index(CourrierDraftRepository $courrierDraftRepository): Response
    {
        return $this->render('courrier/index.html.twig', [
            'courriersEnValidation' => $courrierDraftRepository->findForRhByStatuses(['en_validation']),
            'courriersValides' => $courrierDraftRepository->findForRhByStatuses(['valide']),
        ]);
    }

    #[Route('/generate/{signalementId}', name: 'app_courrier_generate', methods: ['POST'])]
    public function generate(
        int $signalementId,
        Request $request,
        EntityManagerInterface $em,
        AiConfigurationManager $aiConfigurationManager,
        AiSignalementAnalyzer $analyzer,
        AuditLogger $auditLogger
    ): Response
    {
        $signalement = $em->getRepository(Signalement::class)->find($signalementId);
        if (!$signalement) {
            throw $this->createNotFoundException();
        }

        $mode = (string) $request->request->get('mode', 'standard');
        $contenu = $this->generateTemplate($signalement);

        if ($mode === 'ai' && $aiConfigurationManager->getDefaultActiveConfig() !== null) {
            try {
                $analysis = $analyzer->analyze($signalement, $this->getUser());
                $aiDraft = trim((string) ($analysis['courrierDraft'] ?? ''));
                if ($aiDraft !== '') {
                    $contenu = $aiDraft;
                }
                $auditLogger->log(
                    'courrier.generated.ai',
                    sprintf('Brouillon IA généré pour le signalement #%d.', $signalement->getId()),
                    ['provider' => $analysis['provider'] ?? null],
                    $signalement,
                    $this->getUser()
                );
            } catch (\Throwable $e) {
                $this->addFlash('warning', 'Génération IA indisponible, bascule sur le modèle standard : ' . $e->getMessage());
            }
        }

        $courrier = new CourrierDraft();
        $courrier->setSignalement($signalement);
        $courrier->setContenu($contenu);
        $em->persist($courrier);
        $em->flush();

        $this->addFlash('success', $mode === 'ai' ? 'Brouillon de courrier IA généré.' : 'Brouillon de courrier généré.');
        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    #[Route('/{id}', name: 'app_courrier_show', methods: ['GET'])]
    public function show(CourrierDraft $courrier): Response
    {
        return $this->render('courrier/show.html.twig', ['courrier' => $courrier]);
    }

    #[Route('/{id}/pdf', name: 'app_courrier_pdf', methods: ['GET'])]
    public function pdf(CourrierDraft $courrier, PdfGenerator $pdfGenerator): Response
    {
        $pdf = $pdfGenerator->generateFromText(
            'Courrier RATP - ' . $courrier->getSignalement()->getTitre(),
            $courrier->getContenu()
        );

        return new Response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => (new ResponseHeaderBag())
                ->makeDisposition(ResponseHeaderBag::DISPOSITION_ATTACHMENT, 'courrier-' . $courrier->getId() . '.pdf'),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_courrier_edit', methods: ['GET', 'POST'])]
    public function edit(CourrierDraft $courrier, Request $request, EntityManagerInterface $em): Response
    {
        if (!$courrier->isEditable()) {
            $this->addFlash('error', 'Ce courrier ne peut plus être modifié.');
            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        $form = $this->createForm(CourrierDraftType::class, $courrier);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Courrier mis à jour.');
            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        return $this->render('courrier/edit.html.twig', ['form' => $form, 'courrier' => $courrier]);
    }

    #[Route('/{id}/validate', name: 'app_courrier_validate', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function validate(CourrierDraft $courrier, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('courrier-action-' . $courrier->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $courrier->setStatut('valide');
        $courrier->setValidatedBy($this->getUser());
        $em->flush();

        $this->addFlash('success', 'Courrier validé.');
        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    #[Route('/{id}/send', name: 'app_courrier_send', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function send(
        CourrierDraft $courrier,
        Request $request,
        EntityManagerInterface $em,
        MailevaMockClient $mailevaMockClient,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('courrier-action-' . $courrier->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if ($courrier->getStatut() !== 'valide') {
            $this->addFlash('error', 'Seul un courrier validé peut être envoyé.');
            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        $mailevaMockClient->send($courrier);
        $auditLogger->log(
            'courrier.sent.mock_maileva',
            sprintf('Courrier #%d envoyé via Maileva simulé.', $courrier->getId()),
            ['dispatchStatus' => $courrier->getDispatchStatus()],
            $courrier,
            $this->getUser()
        );
        $em->flush();

        $this->addFlash('success', 'Courrier envoyé via le connecteur Maileva simulé.');
        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    #[Route('/{id}/sync-delivery', name: 'app_courrier_sync_delivery', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function syncDelivery(
        CourrierDraft $courrier,
        Request $request,
        EntityManagerInterface $em,
        MailevaMockClient $mailevaMockClient,
        AuditLogger $auditLogger
    ): Response {
        if (!$this->isCsrfTokenValid('courrier-action-' . $courrier->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        if (!$courrier->isDispatched()) {
            $this->addFlash('warning', 'Le courrier doit d’abord être envoyé avant synchronisation.');
            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        if ($courrier->isDispatchComplete()) {
            $this->addFlash('info', 'Le suivi Maileva simulé est déjà arrivé au statut final.');
            return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
        }

        $mailevaMockClient->sync($courrier);
        $auditLogger->log(
            'courrier.synced.mock_maileva',
            sprintf('Courrier #%d synchronisé sur le statut %s.', $courrier->getId(), $courrier->getDispatchStatus()),
            ['dispatchStatus' => $courrier->getDispatchStatus()],
            $courrier,
            $this->getUser()
        );
        $em->flush();

        $this->addFlash('success', 'Statut Maileva simulé synchronisé.');
        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    #[Route('/{id}/request-validation', name: 'app_courrier_request_validation', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function requestValidation(
        CourrierDraft $courrier,
        Request $request,
        EntityManagerInterface $em,
        SignalementNotificationService $notificationService
    ): Response {
        if (!$this->isCsrfTokenValid('courrier-action-' . $courrier->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $courrier->setStatut('en_validation');
        $courrier->setValidatedBy(null);
        $em->flush();

        /** @var \App\Entity\User $user */
        $user = $this->getUser();
        $notificationService->sendCourrierValidationRequestNotification($courrier, $user);

        $this->addFlash('success', 'Courrier envoyé en validation RH.');
        return $this->redirectToRoute('app_signalement_show', ['id' => $courrier->getSignalement()->getId()]);
    }

    #[Route('/{id}/refuse', name: 'app_courrier_refuse', methods: ['POST'])]
    #[IsGranted('ROLE_RH')]
    public function refuse(CourrierDraft $courrier, Request $request, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('courrier-action-' . $courrier->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Token CSRF invalide.');
        }

        $courrier->setStatut('refuse');
        $em->flush();

        $this->addFlash('warning', 'Courrier refusé — retour en brouillon possible.');
        return $this->redirectToRoute('app_courrier_show', ['id' => $courrier->getId()]);
    }

    private function generateTemplate(Signalement $signalement): string
    {
        $s = $signalement;
        $agent = $s->getAgent();
        $date = (new \DateTime())->format('d/m/Y');
        $dateFait = $s->getDateFait()->format('d/m/Y');
        $gravite = $s->getGravite() ? ucfirst($s->getGravite()) : 'N/A';
        $canal = $s->getCanalLabel();
        $statut = $s->getStatutLabel();
        $agentNom = $agent ? $agent->getPrenom() . ' ' . $agent->getNom() : $s->getAgentDisplayName();
        $agentMatricule = $agent ? $agent->getMatricule() : 'N/A';
        $agentCentre = $agent ? ($agent->getCentre() ?? 'N/A') : 'À identifier';

        return <<<EOT
RATP — Service Ressources Humaines
Date : {$date}

Objet : Notification concernant l'agent {$agentNom} (matricule {$agentMatricule})

Madame, Monsieur,

Nous vous informons qu'un signalement de type « {$s->getType()} » a été enregistré
le {$dateFait} concernant l'agent {$agentNom},
matricule {$agentMatricule}, affecté au centre {$agentCentre}.

Faits rapportés :
{$s->getDescription()}

---
Gravité : {$gravite}
Canal de réception : {$canal}
Statut actuel du dossier : {$statut}
---

Ce courrier est un projet généré automatiquement à partir des données du dossier.
Il doit être relu, corrigé si nécessaire, puis validé par le service RH ou juridique
avant tout envoi officiel.

Service Ressources Humaines — RATP
EOT;
    }
}

<?php

namespace App\Controller;

use App\Entity\Agent;
use App\Form\AgentImportType;
use App\Form\AgentType;
use App\Repository\AgentRepository;
use App\Repository\SignalementRepository;
use App\Service\AgentCsvImporter;
use App\Service\PdfGenerator;
use App\Service\ScoreCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/agent')]
#[IsGranted('ROLE_USER')]
class AgentController extends AbstractController
{
    #[Route('/', name: 'app_agent_index', methods: ['GET'])]
    public function index(AgentRepository $agentRepo, ScoreCalculator $scoreCalc): Response
    {
        $agentsWithScores = $agentRepo->findAllWithScores();
        $importForm = $this->createForm(AgentImportType::class, null, [
            'action' => $this->generateUrl('app_agent_import_csv'),
            'method' => 'POST',
        ])->createView();

        return $this->render('agent/index.html.twig', [
            'agentsWithScores' => $agentsWithScores,
            'scoreCalc'        => $scoreCalc,
            'importForm'       => $importForm,
        ]);
    }

    #[Route('/new', name: 'app_agent_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $agent = new Agent();
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($agent);
            $em->flush();

            $this->addFlash('success', 'Agent créé avec succès.');
            return $this->redirectToRoute('app_agent_show', ['id' => $agent->getId()]);
        }

        return $this->render('agent/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_agent_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Agent $agent, SignalementRepository $signalementRepo, ScoreCalculator $scoreCalc): Response
    {
        $signalements = $signalementRepo->findByAgent($agent);
        $score = $scoreCalc->calculate($agent);

        $nbIncidents = count(array_filter($signalements, fn($s) => $s->getType() === 'incident'));
        $nbPositifs  = count(array_filter($signalements, fn($s) => $s->getType() === 'positif'));
        $nbLast90    = count($signalementRepo->findIncidentsLast90Days($agent));

        return $this->render('agent/show.html.twig', [
            'agent'        => $agent,
            'signalements' => $signalements,
            'score'        => $score,
            'scoreCalc'    => $scoreCalc,
            'nbIncidents'  => $nbIncidents,
            'nbPositifs'   => $nbPositifs,
            'nbLast90'     => $nbLast90,
        ]);
    }

    #[Route('/{id}/export-pdf', name: 'app_agent_export_pdf', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function exportPdf(
        Agent $agent,
        SignalementRepository $signalementRepo,
        ScoreCalculator $scoreCalc,
        PdfGenerator $pdfGenerator
    ): Response {
        if (!$this->isGranted('ROLE_MANAGER') && !$this->isGranted('ROLE_RH')) {
            throw $this->createAccessDeniedException();
        }

        $signalements = $signalementRepo->findByAgent($agent);
        $score = $scoreCalc->calculate($agent);
        $nbLast90 = count($signalementRepo->findIncidentsLast90Days($agent));

        $lines = [];
        $lines[] = 'DOSSIER AGENT — RATP';
        $lines[] = str_repeat('=', 50);
        $lines[] = '';
        $lines[] = 'Agent         : ' . $agent->getFullName();
        $lines[] = 'Matricule     : ' . $agent->getMatricule();
        $lines[] = 'Centre        : ' . ($agent->getCentre() ?? 'Non renseigné');
        $lines[] = 'Date naissanc.: ' . ($agent->getDateNaissance() ? $agent->getDateNaissance()->format('d/m/Y') : 'Non renseignée');
        $lines[] = 'Statut        : ' . ($agent->isActif() ? 'Actif' : 'Inactif');
        $lines[] = 'Date export   : ' . (new \DateTimeImmutable())->format('d/m/Y H:i');
        $lines[] = '';
        $lines[] = 'SCORE DE SURVEILLANCE';
        $lines[] = str_repeat('-', 30);
        $lines[] = sprintf('Niveau : %d/4 — %s', $score, $scoreCalc->getLevelLabel($score));
        $lines[] = sprintf('Incidents sur 90 jours : %d', $nbLast90);
        $lines[] = '';
        $lines[] = 'SYNTHESE';
        $lines[] = str_repeat('-', 30);

        $nbIncidents = count(array_filter($signalements, fn($s) => $s->getType() === 'incident'));
        $nbPositifs  = count(array_filter($signalements, fn($s) => $s->getType() === 'positif'));
        $lines[] = sprintf('Total signalements : %d', count($signalements));
        $lines[] = sprintf('Incidents          : %d', $nbIncidents);
        $lines[] = sprintf('Avis positifs      : %d', $nbPositifs);
        $lines[] = '';
        $lines[] = 'HISTORIQUE DETAILLE';
        $lines[] = str_repeat('-', 30);

        foreach ($signalements as $s) {
            $lines[] = '';
            $lines[] = sprintf(
                '[%s] %s — %s — %s',
                $s->getDateFait()->format('d/m/Y'),
                strtoupper($s->getType()),
                $s->getGravite() ? strtoupper($s->getGravite()) : 'S/O',
                $s->getStatutLabel()
            );
            $lines[] = 'Titre : ' . $s->getTitre();
            $lines[] = 'Canal : ' . $s->getCanalLabel();
            if ($s->getDescription()) {
                $desc = mb_strimwidth(str_replace(["\n", "\r"], ' ', $s->getDescription()), 0, 200, '...');
                $lines[] = 'Description : ' . $desc;
            }
        }

        $lines[] = '';
        $lines[] = str_repeat('=', 50);
        $lines[] = 'Document généré automatiquement — RATP Signalements';

        $content = implode("\n", $lines);
        $title = sprintf('Dossier Agent — %s (%s)', $agent->getFullName(), $agent->getMatricule());
        $pdf = $pdfGenerator->generateFromText($title, $content);

        $filename = sprintf('dossier-agent-%s-%s.pdf', $agent->getMatricule(), date('Ymd'));

        return new Response($pdf, 200, [
            'Content-Type'        => 'application/pdf',
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    #[Route('/import-csv', name: 'app_agent_import_csv', methods: ['POST'])]
    #[IsGranted('ROLE_MANAGER')]
    public function importCsv(Request $request, AgentCsvImporter $importer): Response
    {
        $form = $this->createForm(AgentImportType::class);
        $form->handleRequest($request);

        if (!$form->isSubmitted() || !$form->isValid()) {
            $this->addFlash('error', 'Fichier CSV invalide.');
            return $this->redirectToRoute('app_agent_index');
        }

        $file = $form->get('csvFile')->getData();
        $result = $importer->import($file);

        $this->addFlash('success', sprintf(
            'Import CSV terminé : %d créé(s), %d mis à jour, %d ignoré(s).',
            $result['created'],
            $result['updated'],
            $result['skipped']
        ));

        foreach ($result['errors'] as $error) {
            $this->addFlash('warning', $error);
        }

        return $this->redirectToRoute('app_agent_index');
    }

    #[Route('/{id}/edit', name: 'app_agent_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_MANAGER')]
    public function edit(Agent $agent, Request $request, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(AgentType::class, $agent);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Agent modifié avec succès.');
            return $this->redirectToRoute('app_agent_show', ['id' => $agent->getId()]);
        }

        return $this->render('agent/edit.html.twig', ['form' => $form, 'agent' => $agent]);
    }

    #[Route('/{id}/delete', name: 'app_agent_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Agent $agent, Request $request, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete-agent-' . $agent->getId(), $request->request->get('_token'))) {
            // Soft delete
            $agent->setActif(false);
            $em->flush();
            $this->addFlash('success', 'Agent désactivé.');
        }

        return $this->redirectToRoute('app_agent_index');
    }
}

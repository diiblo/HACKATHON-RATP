<?php

namespace App\Controller;

use App\Entity\HistoriqueStatut;
use App\Entity\Signalement;
use App\Service\AgentPlanningSimulator;
use App\Service\AiPreTriageService;
use App\Service\AuditLogger;
use App\Service\LanguageSimulationService;
use App\Service\SocialInboxSimulator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/social')]
#[IsGranted('ROLE_MANAGER')]
class SocialInboxController extends AbstractController
{
    #[Route('/inbox', name: 'app_social_inbox', methods: ['GET'])]
    public function index(SocialInboxSimulator $socialInboxSimulator): Response
    {
        return $this->render('social/inbox.html.twig', [
            'feed' => $socialInboxSimulator->getFeed(),
        ]);
    }

    #[Route('/import/{id}', name: 'app_social_import', methods: ['POST'])]
    public function import(
        string $id,
        SocialInboxSimulator $socialInboxSimulator,
        AgentPlanningSimulator $agentPlanningSimulator,
        LanguageSimulationService $languageSimulationService,
        EntityManagerInterface $em,
        AuditLogger $auditLogger,
        AiPreTriageService $aiPreTriageService
    ): Response {
        $item = $socialInboxSimulator->find($id);
        if ($item === null) {
            throw $this->createNotFoundException();
        }

        $signalement = new Signalement();
        $occurredAt = new \DateTimeImmutable($item['publishedAt']);
        $signalement->setType('incident')
            ->setCanal('social')
            ->setTitre($item['title'])
            ->setDescription($item['content'])
            ->setTranslatedDescription($languageSimulationService->translateToFrench($item['content'], $item['language']))
            ->setSourceLanguage($item['language'])
            ->setSourcePlatform($item['platform'])
            ->setSourceLine($item['line'])
            ->setSourceVehicle($item['vehicle'])
            ->setSourceStop($item['stop'])
            ->setSourceEntryMode('social_connector_sim')
            ->setDateFait($occurredAt)
            ->setStatut('nouveau')
            ->setCreatedBy($this->getUser())
            ->setAgentDescription(sprintf('Agent à identifier via %s / ligne %s', $item['platform'], $item['line']));

        $planning = $agentPlanningSimulator->resolve($item['line'], $item['vehicle'], $occurredAt);
        if ($planning !== null) {
            $signalement->setAgent($planning['agent']);
        }

        $em->persist($signalement);

        $historique = (new HistoriqueStatut())
            ->setSignalement($signalement)
            ->setUser($this->getUser())
            ->setAncienStatut(null)
            ->setNouveauStatut('nouveau')
            ->setCommentaire(sprintf('Import simulé depuis %s (%s).', $item['platform'], $item['author']));
        $em->persist($historique);

        if ($planning !== null) {
            $historiquePlanning = (new HistoriqueStatut())
                ->setSignalement($signalement)
                ->setUser($this->getUser())
                ->setAncienStatut('nouveau')
                ->setNouveauStatut('qualification')
                ->setCommentaire($planning['reason']);
            $em->persist($historiquePlanning);
            $signalement->setStatut('qualification');
        }

        $em->flush();
        $aiPreTriageService->preTriage($signalement);
        $em->flush();
        $auditLogger->log(
            'social.signalement.imported',
            sprintf('Post %s importé comme signalement #%d.', $item['id'], $signalement->getId()),
            [
                'platform' => $item['platform'],
                'line' => $item['line'],
                'vehicle' => $item['vehicle'],
                'language' => $item['language'],
            ],
            $signalement,
            $this->getUser()
        );

        $this->addFlash('success', 'Post social importé dans le dossier signalement.');
        return $this->redirectToRoute('app_signalement_show', ['id' => $signalement->getId()]);
    }
}

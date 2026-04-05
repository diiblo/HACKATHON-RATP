<?php

namespace App\Service;

use App\Entity\AiProviderConfig;
use App\Entity\Signalement;

class AiPromptBuilder
{
    public function buildSignalementAnalysisMessages(AiProviderConfig $config, Signalement $signalement): array
    {
        $systemPrompt = $config->getSystemPrompt() ?: <<<TXT
Tu es un assistant d'analyse métier pour la RATP.
Réponds strictement en JSON avec les clés :
summary, qualification, risk_factors, recommended_actions, courrier_guidance,
recommended_decision, urgency_level, recommended_status, decision_score,
video_preservation_action, alert_email_subject, alert_email_body,
alert_target_roles, courrier_draft.
TXT;

        $context = $config->getContextTemplate() ?: <<<TXT
Titre : {{titre}}
Type : {{type}}
Gravité : {{gravite}}
Canal : {{canal}}
Agent : {{agent}}
Statut : {{statut}}
Description : {{description}}
Commentaires : {{commentaires}}
Traduction : {{traduction}}
Note vocale : {{note_vocale}}
Source : {{source}}
Vidéo : {{video_timer}}
Plainte : {{plainte}}
TXT;

        $commentaires = [];
        foreach ($signalement->getCommentaires() as $commentaire) {
            $commentaires[] = sprintf('%s : %s', $commentaire->getUser()->getFullName(), $commentaire->getContenu());
        }

        $replacements = [
            '{{titre}}' => $signalement->getTitre(),
            '{{type}}' => $signalement->getType(),
            '{{gravite}}' => $signalement->getGravite() ?? 'N/A',
            '{{canal}}' => $signalement->getCanalLabel(),
            '{{agent}}' => $signalement->getAgentDisplayName(),
            '{{statut}}' => $signalement->getStatutLabel(),
            '{{description}}' => $signalement->getDescription(),
            '{{commentaires}}' => $commentaires === [] ? 'Aucun commentaire.' : implode("\n", $commentaires),
            '{{traduction}}' => $signalement->getTranslatedDescription() ?? 'Aucune traduction.',
            '{{note_vocale}}' => $signalement->getVoiceTranscript() ?? 'Aucune note vocale.',
            '{{source}}' => $signalement->getSourceContextLabel() ?? 'Aucun contexte source structuré.',
            '{{video_timer}}' => $signalement->isIncident()
                ? ($signalement->isVideoEvidenceExpired()
                    ? 'Fenêtre vidéo expirée.'
                    : sprintf('%d heure(s) restantes avant échéance vidéo.', $signalement->getVideoHoursRemaining()))
                : 'Non applicable.',
            '{{plainte}}' => $signalement->hasComplaintProof()
                ? 'Preuve de plainte déposée : oui.'
                : 'Preuve de plainte déposée : non.',
        ];

        $userContent = strtr($context, $replacements) . "\n\nAnalyse demandée : produire une synthèse d'aide à la décision RH / managériale, proposer une décision prudente, qualifier l'urgence, préciser si une action immédiate de conservation vidéo est nécessaire, suggérer le prochain statut du dossier, donner un score de priorité de 1 à 4, préparer un brouillon de courrier réutilisable, et préparer un email d'alerte prêt à envoyer aux bons responsables.";

        return [
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userContent],
        ];
    }
}

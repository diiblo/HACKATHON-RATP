<?php

namespace App\Service;

use App\Entity\CourrierDraft;
use App\Entity\Signalement;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SignalementNotificationService
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly UserRepository $userRepository,
    ) {}

    public function sendStatusTransitionNotification(Signalement $signalement, string $oldStatus, string $newStatus, User $actor): void
    {
        $roles = match ($newStatus) {
            'qualification' => ['ROLE_MANAGER', 'ROLE_ADMIN'],
            'validation' => ['ROLE_RH', 'ROLE_ADMIN'],
            'traite' => ['ROLE_RH', 'ROLE_JURIDIQUE', 'ROLE_ADMIN'],
            'archive' => ['ROLE_MANAGER', 'ROLE_ADMIN'],
            default => ['ROLE_ADMIN'],
        };

        $this->sendToRoles(
            $roles,
            sprintf('[RATP] Signalement #%d - statut %s', $signalement->getId(), $signalement->getStatutLabel()),
            sprintf(
                "Le signalement #%d \"%s\" est passé de %s à %s.\n\nAgent : %s\nCanal : %s\nAction réalisée par : %s",
                $signalement->getId(),
                $signalement->getTitre(),
                Signalement::STATUT_LABELS[$oldStatus] ?? $oldStatus,
                Signalement::STATUT_LABELS[$newStatus] ?? $newStatus,
                $signalement->getAgentDisplayName(),
                $signalement->getCanalLabel(),
                $actor->getFullName()
            )
        );
    }

    public function sendCourrierValidationRequestNotification(CourrierDraft $courrier, User $actor): void
    {
        $signalement = $courrier->getSignalement();

        $this->sendToRoles(
            ['ROLE_RH', 'ROLE_ADMIN'],
            sprintf('[RATP] Courrier #%d à valider', $courrier->getId()),
            sprintf(
                "Le courrier #%d du signalement #%d a été envoyé en validation.\n\nTitre : %s\nAgent : %s\nDemandeur : %s",
                $courrier->getId(),
                $signalement->getId(),
                $signalement->getTitre(),
                $signalement->getAgentDisplayName(),
                $actor->getFullName()
            )
        );
    }

    public function sendAiDecisionAlert(Signalement $signalement, array $analysis, User $actor): void
    {
        $roles = $analysis['alertTargetRoles'] ?? ['ROLE_MANAGER', 'ROLE_RH'];
        $subject = trim((string) ($analysis['alertEmailSubject'] ?? ''));
        $body = trim((string) ($analysis['alertEmailBody'] ?? ''));

        if ($subject === '') {
            $subject = sprintf('[RATP] Alerte décisionnelle signalement #%d', $signalement->getId());
        }

        if ($body === '') {
            $body = sprintf(
                "Signalement #%d\nTitre : %s\nDécision recommandée : %s\nUrgence : %s\n",
                $signalement->getId(),
                $signalement->getTitre(),
                $analysis['recommendedDecision'] ?? 'Non fournie',
                $analysis['urgencyLevel'] ?? 'Non fournie'
            );
        }

        $body .= sprintf(
            "\n\nDossier : #%d\nAgent : %s\nÉmetteur de l'alerte : %s",
            $signalement->getId(),
            $signalement->getAgentDisplayName(),
            $actor->getFullName()
        );

        $this->sendToRoles($roles, $subject, $body);
    }

    private function sendToRoles(array $roles, string $subject, string $body): void
    {
        $users = $this->userRepository->findActiveByRoles($roles);

        foreach ($users as $user) {
            $message = (new Email())
                ->from('no-reply@ratp.local')
                ->to($user->getEmail())
                ->subject($subject)
                ->text($body);

            $this->mailer->send($message);
        }
    }
}

<?php

namespace App\Service;

use App\Entity\CourrierDraft;

class MailevaMockClient
{
    public function send(CourrierDraft $courrier): void
    {
        if ($courrier->getDispatchReference() === null) {
            $courrier->setDispatchReference(sprintf('MLV-%06d-%s', $courrier->getId() ?? 0, (new \DateTimeImmutable())->format('His')));
        }

        if ($courrier->getDispatchedAt() === null) {
            $courrier->setDispatchedAt(new \DateTime());
        }

        if (!$courrier->isDispatched()) {
            $courrier->setDispatchStatus('cree');
            $courrier->addDispatchJournalEntry('cree', 'Courrier pris en charge par le connecteur Maileva simulé.');
        }

        $courrier->setDispatchStatus('envoye');
        $courrier->addDispatchJournalEntry('envoye', 'Courrier marqué comme expédié au prestataire.');
    }

    public function sync(CourrierDraft $courrier): void
    {
        $nextStatus = match ($courrier->getDispatchStatus()) {
            'envoye' => 'distribue',
            'distribue' => 'receptionne',
            'cree' => 'envoye',
            default => null,
        };

        if ($nextStatus === null) {
            return;
        }

        $courrier->setDispatchStatus($nextStatus);
        $courrier->addDispatchJournalEntry(
            $nextStatus,
            match ($nextStatus) {
                'distribue' => 'Le courrier est déclaré distribué / mis à disposition par le connecteur simulé.',
                'receptionne' => 'Le courrier est déclaré réceptionné par le connecteur simulé.',
                default => 'Statut Maileva simulé mis à jour.',
            }
        );
    }
}

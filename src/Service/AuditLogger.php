<?php

namespace App\Service;

use App\Entity\AuditLog;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;

class AuditLogger
{
    public function __construct(
        private readonly EntityManagerInterface $em,
    ) {}

    public function log(
        string $action,
        string $summary,
        array $metadata = [],
        ?object $subject = null,
        ?User $user = null,
        ?string $actorLabel = null
    ): void {
        $log = new AuditLog();
        $log->setAction($action)
            ->setSummary($summary)
            ->setMetadata($metadata)
            ->setActorEmail($user?->getEmail())
            ->setActorLabel($actorLabel ?? $user?->getFullName() ?? 'system');

        if ($subject !== null) {
            $log->setSubjectType((new \ReflectionClass($subject))->getShortName());
            if (method_exists($subject, 'getId')) {
                $log->setSubjectId($subject->getId());
            }
        }

        $this->em->persist($log);
    }
}

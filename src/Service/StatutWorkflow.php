<?php

namespace App\Service;

use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\SecurityBundle\Security;

class StatutWorkflow
{
    const TRANSITIONS = [
        'nouveau'       => ['qualification'],
        'qualification' => ['validation', 'archive'],
        'validation'    => ['traite', 'archive'],
        'traite'        => ['archive'],
        'archive'       => [],
    ];

    const ROLE_REQUIRED = [
        'qualification' => null,
        'validation'    => 'ROLE_RH',
        'traite'        => 'ROLE_RH',
        'archive'       => 'ROLE_MANAGER',
    ];

    public function __construct(private readonly Security $security) {}

    public function getAllowedTransitions(string $currentStatut): array
    {
        $targets = self::TRANSITIONS[$currentStatut] ?? [];
        $allowed = [];

        foreach ($targets as $target) {
            if ($this->canTransition($currentStatut, $target)) {
                $allowed[] = $target;
            }
        }

        return $allowed;
    }

    public function canTransition(string $from, string $to): bool
    {
        $targets = self::TRANSITIONS[$from] ?? [];
        if (!in_array($to, $targets)) {
            return false;
        }

        $roleRequired = self::ROLE_REQUIRED[$to] ?? null;

        if ($roleRequired === null) {
            // N'importe quel utilisateur connecté
            return $this->security->isGranted('ROLE_USER');
        }

        // Cas spécial : 'traite' autorisé aussi pour ROLE_JURIDIQUE
        if ($to === 'traite') {
            return $this->security->isGranted('ROLE_RH')
                || $this->security->isGranted('ROLE_JURIDIQUE')
                || $this->security->isGranted('ROLE_ADMIN');
        }

        return $this->security->isGranted($roleRequired);
    }

    public function getTransitionLabel(string $statut): string
    {
        return \App\Entity\Signalement::STATUT_LABELS[$statut] ?? $statut;
    }

    public function getTransitionBtnClass(string $statut): string
    {
        $classes = [
            'qualification' => 'btn-info',
            'validation'    => 'btn-warning',
            'traite'        => 'btn-success',
            'archive'       => 'btn-secondary',
        ];
        return $classes[$statut] ?? 'btn-primary';
    }
}

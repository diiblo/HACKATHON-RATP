<?php

namespace App\Service;

use App\Entity\Agent;
use App\Repository\AgentRepository;

class AgentPlanningSimulator
{
    public function __construct(
        private readonly AgentRepository $agentRepository,
    ) {}

    public function resolve(?string $line, ?string $vehicle, ?\DateTimeInterface $occurredAt): ?array
    {
        $line = trim((string) $line);
        $vehicle = trim((string) $vehicle);

        $matricule = match (true) {
            $line === '72' && $vehicle === '4589' && $this->isMorning($occurredAt) => 'RAT001',
            $line === '72' && $vehicle === '4589' => 'RAT002',
            $line === '183' && $vehicle === '7712' => 'RAT005',
            $line === '38' && $vehicle === '3811' => 'RAT003',
            $line === '91' && $vehicle === '9134' => 'RAT004',
            default => null,
        };

        if ($matricule === null) {
            return null;
        }

        $agent = $this->agentRepository->findOneBy(['matricule' => $matricule]);
        if (!$agent instanceof Agent) {
            return null;
        }

        return [
            'agent' => $agent,
            'confidence' => ($line !== '' && $vehicle !== '') ? 92 : 68,
            'reason' => sprintf('Affectation simulée planning temps réel pour ligne %s / véhicule %s.', $line ?: 'N/A', $vehicle ?: 'N/A'),
        ];
    }

    private function isMorning(?\DateTimeInterface $occurredAt): bool
    {
        if (!$occurredAt instanceof \DateTimeInterface) {
            return true;
        }

        return (int) $occurredAt->format('H') < 13;
    }
}

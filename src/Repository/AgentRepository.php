<?php

namespace App\Repository;

use App\Entity\Agent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AgentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Agent::class);
    }

    /**
     * Calcule le score d'attention d'un agent.
     * Incidents des 90 derniers jours pondérés par gravité.
     * faible=1, moyen=2, grave=3
     */
    public function calculateScore(Agent $agent): int
    {
        $seuil = new \DateTimeImmutable('-90 days');

        $result = $this->getEntityManager()->createQuery(
            'SELECT s.gravite, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.agent = :agent
               AND s.type = :type
               AND s.dateFait >= :seuil
             GROUP BY s.gravite'
        )
        ->setParameter('agent', $agent)
        ->setParameter('type', 'incident')
        ->setParameter('seuil', $seuil)
        ->getResult();

        $weights = ['faible' => 1, 'moyen' => 2, 'grave' => 3];
        $score = 0;
        foreach ($result as $row) {
            $score += ($weights[$row['gravite']] ?? 1) * (int)$row['nb'];
        }

        return $score;
    }

    /**
     * Retourne tous les agents actifs avec leur score calculé en une passe DQL.
     * Retourne un tableau [['agent' => Agent, 'score' => int], ...]
     */
    public function findAllWithScores(): array
    {
        $seuil = new \DateTimeImmutable('-90 days');

        // Récupère tous les agents actifs
        $agents = $this->findBy(['actif' => true], ['nom' => 'ASC']);

        // Récupère les scores agrégés en un seul DQL
        $rawScores = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(s.agent) as agent_id, s.gravite, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.type = :type
               AND s.dateFait >= :seuil
             GROUP BY s.agent, s.gravite'
        )
        ->setParameter('type', 'incident')
        ->setParameter('seuil', $seuil)
        ->getResult();

        // Calcule les scores par agent
        $weights = ['faible' => 1, 'moyen' => 2, 'grave' => 3];
        $scoreMap = [];
        foreach ($rawScores as $row) {
            $agentId = $row['agent_id'];
            if (!isset($scoreMap[$agentId])) {
                $scoreMap[$agentId] = 0;
            }
            $scoreMap[$agentId] += ($weights[$row['gravite']] ?? 1) * (int)$row['nb'];
        }

        // Assemble le résultat
        $result = [];
        foreach ($agents as $agent) {
            $result[] = [
                'agent' => $agent,
                'score' => $scoreMap[$agent->getId()] ?? 0,
            ];
        }

        // Trie par score décroissant
        usort($result, fn($a, $b) => $b['score'] - $a['score']);

        return $result;
    }
}

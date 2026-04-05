<?php

namespace App\Repository;

use App\Entity\Agent;
use App\Entity\Signalement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

class SignalementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Signalement::class);
    }

    /**
     * Liste filtrée des signalements.
     * Filtres possibles : statut, type, gravite, agent (int id), centre (string)
     */
    public function findFilteredList(array $filters = []): array
    {
        return $this->createFilteredListQueryBuilder($filters)
            ->getQuery()
            ->getResult();
    }

    /**
     * Liste filtrée paginée des signalements.
     */
    public function paginateFilteredList(array $filters = [], int $page = 1, int $perPage = 10): array
    {
        $page = max(1, $page);
        $perPage = max(1, $perPage);

        $paginator = new Paginator(
            $this->createFilteredListQueryBuilder($filters)
                ->setFirstResult(($page - 1) * $perPage)
                ->setMaxResults($perPage)
                ->getQuery()
        );

        $totalItems = count($paginator);
        $totalPages = max(1, (int) ceil($totalItems / $perPage));

        if ($page > $totalPages) {
            $page = $totalPages;
            $paginator = new Paginator(
                $this->createFilteredListQueryBuilder($filters)
                    ->setFirstResult(($page - 1) * $perPage)
                    ->setMaxResults($perPage)
                    ->getQuery()
            );
        }

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'totalItems' => $totalItems,
            'totalPages' => $totalPages,
            'currentPage' => $page,
            'perPage' => $perPage,
        ];
    }

    /**
     * Les N signalements les plus récents pour le dashboard.
     */
    public function findRecentForDashboard(int $limit = 10): array
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.agent', 'a')
            ->addSelect('a')
            ->orderBy('s.dateFait', 'DESC')
            ->addOrderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Comptage par statut.
     * Retourne ['nouveau' => N, 'qualification' => N, ...]
     */
    public function countByStatut(): array
    {
        $counts = $this->countGroupedByField('statut', Signalement::STATUTS);
        $counts['total'] = array_sum($counts);

        return $counts;
    }

    public function countByType(): array
    {
        return $this->countGroupedByField('type', Signalement::TYPES);
    }

    public function countByGravite(): array
    {
        return $this->countGroupedByField('gravite', Signalement::GRAVITES);
    }

    /**
     * Incidents des 90 derniers jours pour un agent (pour calcul score).
     */
    public function findIncidentsLast90Days(Agent $agent): array
    {
        $seuil = new \DateTimeImmutable('-90 days');

        return $this->createQueryBuilder('s')
            ->where('s.agent = :agent')
            ->andWhere('s.type = :type')
            ->andWhere('s.dateFait >= :seuil')
            ->setParameter('agent', $agent)
            ->setParameter('type', 'incident')
            ->setParameter('seuil', $seuil)
            ->getQuery()
            ->getResult();
    }

    /**
     * Tous les signalements d'un agent, ordonnés par date décroissante.
     */
    public function findByAgent(Agent $agent): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.agent = :agent')
            ->setParameter('agent', $agent)
            ->orderBy('s.dateFait', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Nombre d'agents dont le score brut (90 jours) atteint ou dépasse le seuil.
     * Logique : faible=1pt, moyen=2pts, grave=3pts — cohérente avec ScoreCalculator.
     */
    public function countAlertingAgents(int $seuilScore = 5): int
    {
        $seuil = new \DateTimeImmutable('-90 days');

        $rows = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(s.agent) as agent_id, s.gravite, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.type = :type AND s.dateFait >= :seuil AND s.agent IS NOT NULL
             GROUP BY s.agent, s.gravite'
        )
        ->setParameter('type', 'incident')
        ->setParameter('seuil', $seuil)
        ->getResult();

        $weights = ['faible' => 1, 'moyen' => 2, 'grave' => 3];
        $scoreMap = [];
        foreach ($rows as $row) {
            $id = $row['agent_id'];
            $scoreMap[$id] = ($scoreMap[$id] ?? 0) + ($weights[$row['gravite']] ?? 1) * (int) $row['nb'];
        }

        return count(array_filter($scoreMap, fn(int $s) => $s >= $seuilScore));
    }

    private function createFilteredListQueryBuilder(array $filters = []): QueryBuilder
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.agent', 'a')
            ->addSelect('a')
            ->orderBy('s.createdAt', 'DESC');

        if (!empty($filters['q'])) {
            $qb->andWhere('LOWER(s.titre) LIKE :q OR LOWER(s.description) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower(trim((string) $filters['q'])) . '%');
        }

        if (!empty($filters['statut'])) {
            $qb->andWhere('s.statut = :statut')->setParameter('statut', $filters['statut']);
        }
        if (!empty($filters['type'])) {
            $qb->andWhere('s.type = :type')->setParameter('type', $filters['type']);
        }
        if (!empty($filters['gravite'])) {
            $qb->andWhere('s.gravite = :gravite')->setParameter('gravite', $filters['gravite']);
        }
        if (!empty($filters['agent'])) {
            $qb->andWhere('s.agent = :agent')->setParameter('agent', (int) $filters['agent']);
        }
        if (!empty($filters['centre'])) {
            $qb->andWhere('a.centre = :centre')->setParameter('centre', $filters['centre']);
        }
        if (!empty($filters['date_from'])) {
            $qb->andWhere('s.dateFait >= :dateFrom')
                ->setParameter('dateFrom', new \DateTimeImmutable($filters['date_from'] . ' 00:00:00'));
        }
        if (!empty($filters['date_to'])) {
            $qb->andWhere('s.dateFait <= :dateTo')
                ->setParameter('dateTo', new \DateTimeImmutable($filters['date_to'] . ' 23:59:59'));
        }

        return $qb;
    }

    /**
     * Nombre de signalements créés depuis une date donnée (pour le polling live).
     */
    public function countCreatedSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->where('s.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Statistiques agrégées pour l'analyse de tendances IA.
     * Compare les 30 derniers jours aux 30 jours précédents.
     */
    public function findTrendStats(): array
    {
        $now = new \DateTimeImmutable();
        $day30 = $now->modify('-30 days');
        $day60 = $now->modify('-60 days');

        $current = $this->getEntityManager()->createQuery(
            'SELECT s.type, s.gravite, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.dateFait >= :from
             GROUP BY s.type, s.gravite'
        )->setParameter('from', $day30)->getResult();

        $previous = $this->getEntityManager()->createQuery(
            'SELECT s.type, s.gravite, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.dateFait >= :from AND s.dateFait < :to
             GROUP BY s.type, s.gravite'
        )->setParameter('from', $day60)->setParameter('to', $day30)->getResult();

        $topAgents = $this->getEntityManager()->createQuery(
            'SELECT IDENTITY(s.agent) as agent_id, a.nom, a.prenom, a.centre, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             JOIN s.agent a
             WHERE s.type = :type AND s.dateFait >= :from AND s.agent IS NOT NULL
             GROUP BY s.agent, a.nom, a.prenom, a.centre
             ORDER BY nb DESC'
        )->setParameter('type', 'incident')->setParameter('from', $day30)->setMaxResults(5)->getResult();

        $canaux = $this->getEntityManager()->createQuery(
            'SELECT s.canal, COUNT(s.id) as nb
             FROM App\Entity\Signalement s
             WHERE s.dateFait >= :from
             GROUP BY s.canal
             ORDER BY nb DESC'
        )->setParameter('from', $day30)->getResult();

        return [
            'current'   => $current,
            'previous'  => $previous,
            'topAgents' => $topAgents,
            'canaux'    => $canaux,
        ];
    }

    private function countGroupedByField(string $field, array $keys): array
    {
        $result = $this->createQueryBuilder('s')
            ->select(sprintf('s.%s as fieldValue, COUNT(s.id) as nb', $field))
            ->where(sprintf('s.%s IS NOT NULL', $field))
            ->groupBy(sprintf('s.%s', $field))
            ->getQuery()
            ->getResult();

        $counts = array_fill_keys($keys, 0);
        foreach ($result as $row) {
            $counts[$row['fieldValue']] = (int) $row['nb'];
        }

        return $counts;
    }
}

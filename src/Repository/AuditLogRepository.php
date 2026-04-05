<?php

namespace App\Repository;

use App\Entity\AuditLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    public function findLatest(int $limit = 100): array
    {
        return $this->createQueryBuilder('a')
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countByActionPrefix(string $prefix): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.action LIKE :prefix')
            ->setParameter('prefix', $prefix . '%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findLatestByActionPrefixes(array $prefixes, int $limit = 10): array
    {
        $qb = $this->createQueryBuilder('a');
        $orX = $qb->expr()->orX();

        foreach (array_values($prefixes) as $index => $prefix) {
            $param = 'prefix_' . $index;
            $orX->add($qb->expr()->like('a.action', ':' . $param));
            $qb->setParameter($param, $prefix . '%');
        }

        return $qb
            ->andWhere($orX)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

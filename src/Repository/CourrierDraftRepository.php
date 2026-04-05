<?php

namespace App\Repository;

use App\Entity\CourrierDraft;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CourrierDraftRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CourrierDraft::class);
    }

    /**
     * @return CourrierDraft[]
     */
    public function findForRhByStatuses(array $statuses): array
    {
        return $this->createQueryBuilder('c')
            ->addSelect('s', 'a', 'v')
            ->join('c.signalement', 's')
            ->leftJoin('s.agent', 'a')
            ->leftJoin('c.validatedBy', 'v')
            ->andWhere('c.statut IN (:statuses)')
            ->setParameter('statuses', $statuses)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countByStatus(string $status): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.statut = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }
}

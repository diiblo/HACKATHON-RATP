<?php

namespace App\Repository;

use App\Entity\AiProviderConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class AiProviderConfigRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AiProviderConfig::class);
    }

    public function findDefaultActive(): ?AiProviderConfig
    {
        return $this->findOneBy(['active' => true, 'isDefault' => true]);
    }

    /**
     * @return AiProviderConfig[]
     */
    public function findActiveFailoverChain(): array
    {
        $configs = $this->createQueryBuilder('c')
            ->andWhere('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.isDefault', 'DESC')
            ->addOrderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult();

        return array_values(array_filter($configs, static fn($config) => $config instanceof AiProviderConfig));
    }
}

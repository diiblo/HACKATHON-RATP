<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\PasswordUpgraderInterface;

class UserRepository extends ServiceEntityRepository implements PasswordUpgraderInterface
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function upgradePassword(PasswordAuthenticatedUserInterface $user, string $newHashedPassword): void
    {
        if (!$user instanceof User) {
            throw new UnsupportedUserException(sprintf('Instances of "%s" are not supported.', $user::class));
        }

        $user->setPassword($newHashedPassword);
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    /**
     * Retourne tous les utilisateurs actifs ayant au moins un des rôles donnés.
     * Sur PostgreSQL, utilise l'opérateur @> (jsonb contains) pour l'exactitude.
     * Sur SQLite (tests), replie sur un LIKE sur la représentation JSON.
     *
     * @param string[] $roles
     * @return User[]
     */
    public function findActiveByRoles(array $roles): array
    {
        if ($roles === []) {
            return [];
        }

        $conn = $this->getEntityManager()->getConnection();
        $platform = $conn->getDatabasePlatform();
        $conditions = [];
        $params = ['actif' => true];

        if ($platform instanceof \Doctrine\DBAL\Platforms\PostgreSQLPlatform) {
            foreach ($roles as $i => $role) {
                $param = 'role_' . $i;
                $conditions[] = "roles::jsonb @> :{$param}::jsonb";
                $params[$param] = json_encode([$role]);
            }
        } else {
            // Fallback SQLite/autre : LIKE sur la représentation JSON sérialisée
            foreach ($roles as $i => $role) {
                $param = 'role_' . $i;
                $conditions[] = "roles LIKE :{$param}";
                $params[$param] = '%"' . $role . '"%';
            }
        }

        $sql = 'SELECT id FROM app_user WHERE actif = :actif AND (' . implode(' OR ', $conditions) . ')';
        $ids = array_column($conn->executeQuery($sql, $params)->fetchAllAssociative(), 'id');

        if ($ids === []) {
            return [];
        }

        return $this->createQueryBuilder('u')
            ->where('u.id IN (:ids)')
            ->setParameter('ids', $ids)
            ->getQuery()
            ->getResult();
    }

    /**
     * Retourne le premier utilisateur système actif (ROLE_ADMIN ou ROLE_DEV).
     * Utilisé par AiPreTriageService comme auteur technique des commentaires IA.
     */
    public function findFirstSystemUser(): ?User
    {
        return $this->findActiveByRoles(['ROLE_ADMIN', 'ROLE_DEV'])[0] ?? null;
    }
}

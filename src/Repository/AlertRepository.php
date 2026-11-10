<?php

namespace App\Repository;

use App\Entity\Alert;
use App\Entity\Horse;
use App\Entity\User;
use App\Enum\AlertType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Alert>
 */
class AlertRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Alert::class);
    }

    /** @return list<Alert> */
    public function findForOwner(User $owner, bool $openOnly, int $limit = 100): array
    {
        $qb = $this->ownerQuery($owner)
            ->orderBy('a.triggeredAt', 'DESC')
            ->setMaxResults($limit);

        if ($openOnly) {
            $qb->andWhere('a.acknowledgedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    public function countOpenForOwner(User $owner): int
    {
        return (int) $this->ownerQuery($owner)
            ->select('COUNT(a.id)')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** @return array<int, int> nombre d'alertes ouvertes par id de cheval */
    public function countOpenByHorse(User $owner): array
    {
        $rows = $this->ownerQuery($owner)
            ->select('IDENTITY(a.horse) AS horseId', 'COUNT(a.id) AS total')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->groupBy('a.horse')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $r) => [(int) $r['horseId'], (int) $r['total']], $rows), 1, 0);
    }

    /** @return list<Alert> */
    public function findRecentForHorse(Horse $horse, int $limit = 10): array
    {
        return $this->findBy(['horse' => $horse], ['triggeredAt' => 'DESC'], $limit);
    }

    /** Évite d'envoyer dix fois la même alerte tant que la première n'est pas traitée. */
    public function hasOpenSince(Horse $horse, AlertType $type, \DateTimeImmutable $since): bool
    {
        return (bool) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->andWhere('a.horse = :horse')
            ->andWhere('a.type = :type')
            ->andWhere('a.acknowledgedAt IS NULL')
            ->andWhere('a.triggeredAt >= :since')
            ->setParameter('horse', $horse)
            ->setParameter('type', $type)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    private function ownerQuery(User $owner): QueryBuilder
    {
        return $this->createQueryBuilder('a')
            ->addSelect('h')
            ->join('a.horse', 'h')
            ->andWhere('h.owner = :owner')
            ->setParameter('owner', $owner);
    }
}

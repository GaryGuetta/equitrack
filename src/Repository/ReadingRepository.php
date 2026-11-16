<?php

namespace App\Repository;

use App\Entity\Horse;
use App\Entity\Reading;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reading>
 */
class ReadingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reading::class);
    }

    public function findLatest(Horse $horse): ?Reading
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.horse = :horse')
            ->setParameter('horse', $horse)
            ->orderBy('r.recordedAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Dernière mesure de chaque cheval, en une requête, indexée par id de cheval.
     *
     * @param list<Horse> $horses
     *
     * @return array<int, Reading>
     */
    public function findLatestForHorses(array $horses): array
    {
        if ([] === $horses) {
            return [];
        }

        $readings = $this->createQueryBuilder('r')
            ->andWhere('r.horse IN (:horses)')
            ->andWhere('r.recordedAt = (SELECT MAX(r2.recordedAt) FROM '.Reading::class.' r2 WHERE r2.horse = r.horse)')
            ->setParameter('horses', $horses)
            ->getQuery()
            ->getResult();

        $byHorse = [];
        foreach ($readings as $reading) {
            $byHorse[$reading->getHorse()->getId()] = $reading;
        }

        return $byHorse;
    }

    /** @return list<Reading> du plus ancien au plus récent */
    public function findBetween(Horse $horse, \DateTimeImmutable $from, ?\DateTimeImmutable $to = null): array
    {
        $qb = $this->createQueryBuilder('r')
            ->andWhere('r.horse = :horse')
            ->andWhere('r.recordedAt >= :from')
            ->setParameter('horse', $horse)
            ->setParameter('from', $from)
            ->orderBy('r.recordedAt', 'ASC');

        if (null !== $to) {
            $qb->andWhere('r.recordedAt <= :to')->setParameter('to', $to);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Indices d'activité mesurés dans une même fenêtre horaire sur les jours précédents,
     * groupés par jour : sert de référence au comportement habituel du cheval (F8).
     *
     * @return array<string, list<float>> clé = date Y-m-d
     */
    public function findActivityInDailyWindow(Horse $horse, \DateTimeImmutable $windowEnd, int $windowMinutes, int $days): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.recordedAt', 'r.activity')
            ->andWhere('r.horse = :horse')
            ->andWhere('r.activity IS NOT NULL')
            ->setParameter('horse', $horse);

        $windows = $qb->expr()->orX();
        for ($day = 1; $day <= $days; ++$day) {
            $end = $windowEnd->modify("-$day days");
            $windows->add("r.recordedAt > :from$day AND r.recordedAt <= :to$day");
            $qb->setParameter("from$day", $end->modify("-$windowMinutes minutes"));
            $qb->setParameter("to$day", $end);
        }
        $qb->andWhere($windows);

        $byDay = [];
        foreach ($qb->getQuery()->getArrayResult() as $row) {
            $byDay[$row['recordedAt']->format('Y-m-d')][] = (float) $row['activity'];
        }

        return $byDay;
    }

    public function countRollsSince(Horse $horse, \DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COALESCE(SUM(r.rolls), 0)')
            ->andWhere('r.horse = :horse')
            ->andWhere('r.recordedAt >= :since')
            ->setParameter('horse', $horse)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return int nombre de mesures supprimées
     */
    public function deleteOlderThan(\DateTimeImmutable $before): int
    {
        return $this->createQueryBuilder('r')
            ->delete()
            ->andWhere('r.recordedAt < :before')
            ->setParameter('before', $before)
            ->getQuery()
            ->execute();
    }
}

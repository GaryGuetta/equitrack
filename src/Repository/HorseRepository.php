<?php

namespace App\Repository;

use App\Entity\Horse;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Horse>
 */
class HorseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Horse::class);
    }

    /** @return list<Horse> */
    public function findForOwner(User $owner): array
    {
        return $this->createQueryBuilder('h')
            ->addSelect('d')
            ->leftJoin('h.device', 'd')
            ->andWhere('h.owner = :owner')
            ->setParameter('owner', $owner)
            ->orderBy('h.name', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

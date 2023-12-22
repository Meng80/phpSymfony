<?php

namespace App\Repository;


use App\Entity\Result;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ResultRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Result::class);
    }

    public function insert(Result $result):void{
        $this->getEntityManager()->persist($result);
        $this->getEntityManager()->flush();
    }
    public function remove(Result $result): void{
        $this->getEntityManager()->remove($result);
        $this->getEntityManager()->flush();
    }

}
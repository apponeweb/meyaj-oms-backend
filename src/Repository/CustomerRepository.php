<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Customer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Customer>
 */
class CustomerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Customer::class);
    }

    /**
     * @return Customer[] Returns an array of Customer objects
     */
    public function findByNameOrPhone(string $query): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.name LIKE :query')
            ->orWhere('c.phone LIKE :query')
            ->orWhere('c.email LIKE :query')
            ->andWhere('c.active = :active')
            ->setParameter('query', '%' . $query . '%')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByPhone(string $phone): ?Customer
    {
        return $this->createQueryBuilder('c')
            ->where('c.phone = :phone')
            ->andWhere('c.active = :active')
            ->setParameter('phone', $phone)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findOneByEmail(string $email): ?Customer
    {
        return $this->createQueryBuilder('c')
            ->where('c.email = :email')
            ->andWhere('c.active = :active')
            ->setParameter('email', $email)
            ->setParameter('active', true)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findActiveCustomers(): array
    {
        return $this->createQueryBuilder('c')
            ->where('c.active = :active')
            ->setParameter('active', true)
            ->orderBy('c.name', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}

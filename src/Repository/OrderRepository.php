<?php

namespace App\Repository;

use App\Entity\Order;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\QueryBuilder;

/**
 * @extends ServiceEntityRepository<Order>
 */
class OrderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(Order $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find orders by user with most recent first
     */
    public function findByUser(User $user)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.user = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find order by reference
     */
    public function findOneByReference(string $reference): ?Order
    {
        return $this->findOneBy(['reference' => $reference]);
    }

    /**
     * Find orders by status
     */
    public function findByStatus(string $status)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders between two dates
     */
    public function findBetweenDates(\DateTime $startDate, \DateTime $endDate)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdAt BETWEEN :startDate AND :endDate')
            ->setParameter('startDate', $startDate)
            ->setParameter('endDate', $endDate)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find orders with total amount greater than value
     */
    public function findByTotalGreaterThan(float $amount)
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.totalAmount > :amount')
            ->setParameter('amount', $amount)
            ->orderBy('o.totalAmount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the total revenue from all orders
     */
    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('o')
            ->select('SUM(o.totalAmount) as total')
            ->getQuery()
            ->getSingleScalarResult();
            
        return $result ?? 0;
    }

    /**
     * Find the latest orders with limit
     */
    public function findLatest(int $limit = 10)
    {
        return $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get order count by status
     */
    public function countByStatus(string $status): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.status = :status')
            ->setParameter('status', $status)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find orders with pagination
     */
    public function findPaginated(int $page = 1, int $limit = 10)
    {
        $query = $this->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery();
            
        $query->setFirstResult(($page - 1) * $limit)
              ->setMaxResults($limit);
              
        return $query->getResult();
    }

    /**
     * Find orders matching search query
     */
    public function search(string $term)
    {
        return $this->createQueryBuilder('o')
            ->leftJoin('o.user', 'u')
            ->andWhere('o.reference LIKE :term OR u.email LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get orders created within last X days
     */
    public function findRecentOrders(int $days = 30)
    {
        $date = new \DateTime();
        $date->modify('-' . $days . ' days');
        
        return $this->createQueryBuilder('o')
            ->andWhere('o.createdAt >= :date')
            ->setParameter('date', $date)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
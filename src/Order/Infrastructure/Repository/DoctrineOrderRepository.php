<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Shared\Application\Event\EventBusInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Order>
 */
class DoctrineOrderRepository extends ServiceEntityRepository implements OrderRepositoryInterface
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Order::class);
    }

    public function save(Order $order): void
    {
        $this->getEntityManager()->persist($order);
        $this->getEntityManager()->flush();

        $this->eventBus->execute(...$order->releaseEvents());
    }

    public function findStatusByStatusIdAndIsDelivery(int $statusId, bool $isDelivery): ?OrderStatus
    {
        return $this->getEntityManager()
            ->getRepository(OrderStatus::class)
            ->findOneBy([
                'statusId' => $statusId,
                'isDelivery' => $isDelivery,
            ])
        ;
    }
}

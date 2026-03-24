<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Repository;

use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Exception\CartAlreadyExistsException;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Shared\Application\Event\EventBusInterface;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Cart>
 */
class DoctrineCartRepository extends ServiceEntityRepository implements CartRepositoryInterface
{
    public function __construct(
        private readonly EventBusInterface $eventBus,
        ManagerRegistry $registry,
    ) {
        parent::__construct($registry, Cart::class);
    }

    public function save(Cart $cart): void
    {
        try {
            $this->getEntityManager()->persist($cart);
            $this->getEntityManager()->flush();
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException) {
            throw CartAlreadyExistsException::forUser($cart->getUserId());
        }

        $this->eventBus->execute(...$cart->releaseEvents());
    }

    public function lockOptimistic(Cart $cart): void
    {
        $this->getEntityManager()->lock($cart, LockMode::OPTIMISTIC, $cart->getVersion());
    }

    public function findActiveByUserIdAndRegion(int $userId, int $regionCode): ?Cart
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.cartItems', 'ci')
            ->addSelect('ci')
            ->where('c.userId = :userId')
            ->andWhere('c.region.regionCode = :regionCode')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->setParameter('regionCode', $regionCode)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}

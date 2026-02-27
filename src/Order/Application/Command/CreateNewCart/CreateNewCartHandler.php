<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CreateNewCart;

use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\OrderDate;
use App\Order\Domain\ValueObject\Type;
use App\Shared\Application\Command\CommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class CreateNewCartHandler implements CommandHandlerInterface
{
    private const int DEFAULT_SHOP_ID = 0;

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CreateNewCartCommand $command): void
    {
        $region = $command->region;
        $userId = $command->userId;

        try {
            $cart = new Cart(
                region: $region,
                type: Type::default(),
                orderDate: OrderDate::createDefault(),
                createdAt: new \DateTimeImmutable(),
                updatedAt: new \DateTimeImmutable(),
                totalCost: Cost::zero(),
                userId: $userId,
                shopNum: self::DEFAULT_SHOP_ID,
            );

            $this->cartRepository->save($cart);
            $this->logger->notice('A new cart has been created.', [
                'id' => $cart->getId(),
                'region' => $cart->getRegion()->getRegionCode(),
                'order_date' => $cart->getOrderDate()->getOrderDate()->format(\DateTimeInterface::RFC3339),
                'user_id' => $cart->getUserId(),
            ]);
        } catch (\Throwable $exception) {
            $this->logger->error('Error while cart creating.', [
                'user_id' => $userId,
                'region' => $region->getRegionCode(),
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}

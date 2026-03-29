<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CartSetUp;

use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\OrderDate;
use App\Order\Domain\ValueObject\Type;
use App\Shared\Application\Command\CommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class CartSetUpHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(CartSetUpCommand $command): void
    {
        $cart = $this->cartRepository->findActiveByUserIdAndRegion($command->userId, $command->regionCode);

        if ($cart === null) {
            $this->logger->warning('Cart not found when trying to set up.', [
                'user_id' => $command->userId,
                'region' => $command->regionCode,
            ]);

            return;
        }

        $this->cartRepository->lockOptimistic($cart);

        $orderDate = OrderDate::create($command->orderDate);
        $type = Type::create($command->isDelivery, $command->orderDate);

        $cart->setType($type);
        $cart->setOrderDate($orderDate);
        $cart->setShopNum($command->shopId);

        $this->cartRepository->save($cart);

        $this->logger->notice('Cart set up.', [
            'user_id' => $command->userId,
            'region' => $command->regionCode,
        ]);
    }
}

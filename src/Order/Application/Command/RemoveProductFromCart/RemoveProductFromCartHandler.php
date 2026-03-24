<?php

declare(strict_types=1);

namespace App\Order\Application\Command\RemoveProductFromCart;

use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class RemoveProductFromCartHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(RemoveProductFromCartCommand $command): void
    {
        $cart = $this->cartRepository->findActiveByUserIdAndRegion($command->userId, $command->regionCode);

        if ($cart === null) {
            $this->logger->warning('Cart not found when trying to remove product.', [
                'user_id' => $command->userId,
                'region' => $command->regionCode,
                'sup_code' => $command->supCode,
            ]);

            return;
        }

        $item = $cart->findItemBySupCode($command->supCode);

        if ($item === null) {
            $this->logger->warning('Product not found in cart when trying to remove.', [
                'user_id' => $command->userId,
                'region' => $command->regionCode,
                'sup_code' => $command->supCode,
            ]);

            return;
        }

        $cart->removeCartItem($item);
        $cart->recalculateTotalCost();
        $this->cartRepository->save($cart);

        $this->logger->notice('Product removed from cart.', [
            'sup_code' => $command->supCode,
            'user_id' => $command->userId,
        ]);
    }
}

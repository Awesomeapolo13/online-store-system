<?php

declare(strict_types=1);

namespace App\Order\Application\Command\AddProductToCart;

use App\Order\Application\Api\Product\Dto\GetProductRequest;
use App\Order\Application\Api\Product\Exception\ProductApiException;
use App\Order\Application\Api\Product\ProductApiInterface;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Exception\ProductNotAvailableForOrderException;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use App\Shared\Application\Command\CommandHandlerInterface;
use Psr\Log\LoggerInterface;

final readonly class AddProductToCartHandler implements CommandHandlerInterface
{
    private const int DEFAULT_SHOP_NUMBER = 0;
    private const string PRODUCT_NOT_FOUND_ERROR_CODE = '1001';

    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private ProductApiInterface $productApi,
        private LoggerInterface $logger,
    ) {
    }

    public function __invoke(AddProductToCartCommand $command): void
    {
        $cart = $this->cartRepository->findActiveByUserIdAndRegion($command->userId, $command->regionCode);

        if ($cart === null) {
            $this->logger->warning('Cart not found when trying to add product.', [
                'user_id' => $command->userId,
                'region' => $command->regionCode,
                'sup_code' => $command->supCode,
            ]);

            return;
        }

        $this->cartRepository->lockOptimistic($cart);

        try {
            $productResponse = $this->productApi->getProduct(new GetProductRequest(
                supCode: $command->supCode,
                shopNumber: self::DEFAULT_SHOP_NUMBER,
                region: $command->regionCode,
            ));
        } catch (ProductApiException $e) {
            if ($e->getErrorCode() === self::PRODUCT_NOT_FOUND_ERROR_CODE) {
                $existingItem = $cart->findItemBySupCode($command->supCode);

                if ($existingItem !== null) {
                    $cart->removeCartItem($existingItem);
                    $cart->recalculateTotalCost();
                    $this->cartRepository->save($cart);

                    $this->logger->notice('Product removed from cart due to API error 1001.', [
                        'sup_code' => $command->supCode,
                        'user_id' => $command->userId,
                    ]);
                }
            }

            throw $e;
        }

        if (!$productResponse->isAvailableForOrder) {
            throw ProductNotAvailableForOrderException::forSupCode($command->supCode);
        }

        $perItemPrice = new Price(number_format((float) $productResponse->pricePerItem, 2, '.', ''));
        $totalCost = Cost::fromString(bcmul($productResponse->pricePerItem, (string) $command->quantity, 2));

        $existingItem = $cart->findItemBySupCode($command->supCode);

        if ($existingItem !== null) {
            if ($existingItem->getQuantity() !== $command->quantity) {
                $existingItem->updateQuantityAndCost($command->quantity, $perItemPrice, $totalCost);
            } else {
                return;
            }
        } else {
            $now = new \DateTimeImmutable();
            $cartItem = new CartItem(
                supCode: $command->supCode,
                perItemPrice: $perItemPrice,
                totalCost: $totalCost,
                quantity: $command->quantity,
                createdAt: $now,
                updatedAt: $now,
            );
            $cart->addCartItem($cartItem);
        }

        $cart->recalculateTotalCost();
        $this->cartRepository->save($cart);

        $this->logger->notice('Product added to cart.', [
            'sup_code' => $command->supCode,
            'quantity' => $command->quantity,
            'user_id' => $command->userId,
        ]);
    }
}

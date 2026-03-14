<?php

declare(strict_types=1);

namespace App\Order\Application\Assembler;

use App\Order\Application\Response\CartItemResponse;
use App\Order\Application\Response\CartResponse;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;

final readonly class CartAssembler
{
    public function toResponse(Cart $cart): CartResponse
    {
        $cartItems = $cart->getCartItems()->map(
            fn (CartItem $item) => new CartItemResponse(
                id: $item->getId(),
                supCode: $item->getSupCode(),
                perItemPrice: $item->getPerItemPrice()->getPrice(),
                totalCost: $item->getTotalCost()->getCost(),
                quantity: $item->getQuantity(),
            ),
        )->toArray();

        return new CartResponse(
            id: $cart->getId(),
            userId: $cart->getUserId(),
            shopNum: $cart->getShopNum(),
            region: $cart->getRegion()->getRegionCode(),
            isDelivery: $cart->getType()->isDelivery(),
            isExpress: $cart->getType()->isExpress(),
            orderDate: $cart->getOrderDate()->getOrderDate()->format(\DateTimeInterface::RFC3339),
            totalCost: $cart->getTotalCost()->getCost(),
            cartItems: array_values($cartItems),
        );
    }
}

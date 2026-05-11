<?php

declare(strict_types=1);

namespace App\Order\Application\Assembler;

use App\Order\Application\Response\OrderItemResponse;
use App\Order\Application\Response\OrderResponse;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;

final readonly class OrderAssembler
{
    public function toResponse(Order $order): OrderResponse
    {
        $orderItems = $order->getOrderItems()->map(
            fn (OrderItem $item) => new OrderItemResponse(
                id: $item->getId(),
                supCode: $item->getSupCode(),
                perItemPrice: $item->getPerItemPrice()->getPrice(),
                totalCost: $item->getTotalCost()->getCost(),
                actualTotalCost: $item->getActualTotalCost()->getCost(),
                quantity: $item->getQuantity(),
                actualQuantity: $item->getActualQuantity(),
            ),
        )->toArray();

        return new OrderResponse(
            id: $order->getId(),
            userId: $order->getUserId(),
            region: $order->getRegion()->getRegionCode(),
            isDelivery: $order->getType()->isDelivery(),
            status: $order->getStatus(),
            actualTotalCost: $order->getActualTotalCost()->getCost(),
            orderItems: array_values($orderItems),
        );
    }
}

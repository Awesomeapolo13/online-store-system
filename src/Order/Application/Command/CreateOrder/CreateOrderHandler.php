<?php

declare(strict_types=1);

namespace App\Order\Application\Command\CreateOrder;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Event\OrderCreatedEvent;
use App\Order\Domain\Exception\DomainException;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\Repository\OrderRepositoryInterface;
use App\Order\Domain\ValueObject\Region;
use App\Shared\Application\Command\CommandBusInterface;
use App\Shared\Application\Command\CommandHandlerInterface;
use App\Shared\Application\Event\EventBusInterface;

final readonly class CreateOrderHandler implements CommandHandlerInterface
{
    public function __construct(
        private CartRepositoryInterface $cartRepository,
        private OrderRepositoryInterface $orderRepository,
        private CommandBusInterface $commandBus,
        private EventBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreateOrderCommand $command): void
    {
        $cart = $this->cartRepository->findActiveByUserIdAndRegion($command->userId, $command->regionCode);

        if ($cart === null || $cart->getCartItems()->isEmpty()) {
            throw new DomainException('В вашей корзину отсутствуют продукты для оформления заказа.');
        }

        $isDelivery = $cart->getType()->isDelivery();

        $status = $this->orderRepository->findStatusByStatusIdAndIsDelivery(0, $isDelivery);

        if ($status === null) {
            throw new DomainException('Order status "accepted" not found.');
        }

        $now = new \DateTimeImmutable();

        $order = new Order(
            region: $cart->getRegion(),
            type: $cart->getType(),
            orderDate: $cart->getOrderDate(),
            createdAt: $now,
            updatedAt: $now,
            totalCost: $cart->getTotalCost(),
            actualTotalCost: $cart->getTotalCost(),
            status: $status,
            userId: $command->userId,
            shopNum: $cart->getShopNum(),
        );

        foreach ($cart->getCartItems() as $cartItem) {
            $orderItem = new OrderItem(
                supCode: $cartItem->getSupCode(),
                perItemPrice: $cartItem->getPerItemPrice(),
                totalCost: $cartItem->getTotalCost(),
                actualTotalCost: $cartItem->getTotalCost(),
                quantity: $cartItem->getQuantity(),
                actualQuantity: $cartItem->getQuantity(),
                createdAt: $now,
                updatedAt: $now,
            );
            $order->addOrderItem($orderItem);
        }

        $order->recalculateActualTotalCost();
        $order->recalculateTotalCost();

        // Save order first so it gets its auto-generated ID after flush.
        $this->orderRepository->save($order);

        // Record the event after save so orderId is available.
        $order->recordEvent(new OrderCreatedEvent(
            orderId: $order->getId(),
            userId: $command->userId,
            regionCode: $command->regionCode,
        ));
        $this->eventBus->execute(...$order->releaseEvents());

        $cart->markAsDeleted();
        $this->cartRepository->save($cart);

        $this->commandBus->dispatch(new CreateNewCartCommand($command->userId, new Region($command->regionCode)));
    }
}

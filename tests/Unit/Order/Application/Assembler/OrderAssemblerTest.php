<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Assembler;

use App\Order\Application\Assembler\OrderAssembler;
use App\Order\Application\Response\OrderItemResponse;
use App\Order\Application\Response\OrderResponse;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Entity\OrderStatus;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use PHPUnit\Framework\TestCase;

final class OrderAssemblerTest extends TestCase
{
    private OrderAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new OrderAssembler();
    }

    public function testToResponseWithOrderHavingNoItems(): void
    {
        $status = new OrderStatus(
            statusId: 0,
            title: 'Принят',
            code: 'accepted',
            isDelivery: false,
        );

        $order = new Order(
            userId: 42,
            region: new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value),
            status: $status,
            actualTotalCost: Cost::zero(),
            createdAt: new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-01-01T10:00:00+00:00'),
        );

        $response = $this->assembler->toResponse($order);

        self::assertInstanceOf(OrderResponse::class, $response, 'Response should be an instance of OrderResponse');
        self::assertNull($response->id, 'Order ID should be null before persistence');
        self::assertSame(42, $response->userId, 'User ID should match');
        self::assertSame(RegionCodeEnum::NIZHNY_NOVGOROD->value, $response->region, 'Region code should match');
        self::assertFalse($response->isDelivery, 'isDelivery should match the status');
        self::assertSame('accepted', $response->statusCode, 'Status code should match');
        self::assertSame('0.00', $response->actualTotalCost, 'Actual total cost should be zero');
        self::assertEmpty($response->orderItems, 'orderItems should be empty for an order with no items');
    }

    public function testToResponseWithOrderHavingItems(): void
    {
        $status = new OrderStatus(
            statusId: 0,
            title: 'Принят',
            code: 'accepted',
            isDelivery: true,
        );

        $order = new Order(
            userId: 99,
            region: new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value),
            status: $status,
            actualTotalCost: Cost::zero(),
            createdAt: new \DateTimeImmutable('2026-02-15T08:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-02-15T08:30:00+00:00'),
        );

        $now = new \DateTimeImmutable('2026-02-15T08:00:00+00:00');

        $orderItem = new OrderItem(
            supCode: '123456789012345',
            perItemPrice: new Price('10.00'),
            totalCost: new Cost('10.00'),
            actualTotalCost: new Cost('10.00'),
            quantity: 1,
            actualQuantity: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $order->addOrderItem($orderItem);
        $order->recalculateActualTotalCost();

        $response = $this->assembler->toResponse($order);

        self::assertInstanceOf(OrderResponse::class, $response, 'Response should be an instance of OrderResponse');
        self::assertNull($response->id, 'Order ID should be null before persistence');
        self::assertSame(99, $response->userId, 'User ID should match');
        self::assertSame(RegionCodeEnum::NIZHNY_NOVGOROD->value, $response->region, 'Region code should match');
        self::assertTrue($response->isDelivery, 'isDelivery should be true when status.isDelivery is true');
        self::assertSame('accepted', $response->statusCode, 'Status code should match');
        self::assertSame('10.00', $response->actualTotalCost, 'Actual total cost should equal the sum of item costs');
        self::assertCount(1, $response->orderItems, 'There should be exactly one order item');

        $itemResponse = $response->orderItems[0];
        self::assertInstanceOf(OrderItemResponse::class, $itemResponse, 'Item should be an instance of OrderItemResponse');
        self::assertNull($itemResponse->id, 'OrderItem ID should be null before persistence');
        self::assertSame('123456789012345', $itemResponse->supCode, 'Supplier code should match');
        self::assertSame('10.00', $itemResponse->perItemPrice, 'Per-item price should match');
        self::assertSame('10.00', $itemResponse->totalCost, 'Total cost should match');
        self::assertSame('10.00', $itemResponse->actualTotalCost, 'Actual total cost should match');
        self::assertSame(1, $itemResponse->quantity, 'Quantity should match');
        self::assertSame(1, $itemResponse->actualQuantity, 'Actual quantity should match');
    }

    public function testToResponseWithMultipleItems(): void
    {
        $status = new OrderStatus(
            statusId: 0,
            title: 'Принят',
            code: 'accepted',
            isDelivery: false,
        );

        $order = new Order(
            userId: 7,
            region: new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value),
            status: $status,
            actualTotalCost: Cost::zero(),
            createdAt: new \DateTimeImmutable('2026-03-01T12:00:00+00:00'),
            updatedAt: new \DateTimeImmutable('2026-03-01T12:00:00+00:00'),
        );

        $now = new \DateTimeImmutable('2026-03-01T12:00:00+00:00');

        $itemA = new OrderItem(
            supCode: 'ITEM-A-00001',
            perItemPrice: new Price('25.00'),
            totalCost: new Cost('50.00'),
            actualTotalCost: new Cost('50.00'),
            quantity: 2,
            actualQuantity: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $itemB = new OrderItem(
            supCode: 'ITEM-B-00002',
            perItemPrice: new Price('15.50'),
            totalCost: new Cost('31.00'),
            actualTotalCost: new Cost('31.00'),
            quantity: 2,
            actualQuantity: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $order->addOrderItem($itemA);
        $order->addOrderItem($itemB);
        $order->recalculateActualTotalCost();

        $response = $this->assembler->toResponse($order);

        self::assertCount(2, $response->orderItems, 'There should be exactly two order items');
        self::assertSame('81.00', $response->actualTotalCost, 'Actual total cost should be the sum of both item costs');
        self::assertSame('ITEM-A-00001', $response->orderItems[0]->supCode, 'First item supplier code should match');
        self::assertSame('ITEM-B-00002', $response->orderItems[1]->supCode, 'Second item supplier code should match');
    }
}

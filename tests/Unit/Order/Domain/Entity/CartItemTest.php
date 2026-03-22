<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Entity;

use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use PHPUnit\Framework\TestCase;

final class CartItemTest extends TestCase
{
    // -------------------------------------------------------------------------
    // updateQuantityAndCost
    // -------------------------------------------------------------------------
    public function testUpdateQuantityAndCostUpdatesAllThreeFields(): void
    {
        $now = new \DateTimeImmutable();
        $item = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('10.00'),
            totalCost: Cost::fromString('20.00'),
            quantity: 2,
            createdAt: $now,
            updatedAt: $now,
        );

        $newPrice = new Price('25.00');
        $newCost = Cost::fromString('125.00');

        $item->updateQuantityAndCost(5, $newPrice, $newCost);

        self::assertSame(5, $item->getQuantity(), 'updateQuantityAndCost() must update quantity');
        self::assertSame(
            '25.00',
            $item->getPerItemPrice()->getPrice(),
            'updateQuantityAndCost() must update perItemPrice',
        );
        self::assertSame(
            '125.00',
            $item->getTotalCost()->getCost(),
            'updateQuantityAndCost() must update totalCost',
        );
    }

    public function testUpdateQuantityAndCostUpdatesUpdatedAtTimestamp(): void
    {
        $originalUpdatedAt = new \DateTimeImmutable('2020-01-01T00:00:00+00:00');
        $item = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('10.00'),
            totalCost: Cost::fromString('10.00'),
            quantity: 1,
            createdAt: $originalUpdatedAt,
            updatedAt: $originalUpdatedAt,
        );

        // Sleep is intentionally avoided — we only assert updatedAt is no longer the old value.
        $item->updateQuantityAndCost(3, new Price('10.00'), Cost::fromString('30.00'));

        self::assertNotSame(
            $originalUpdatedAt,
            $item->getUpdatedAt(),
            'updateQuantityAndCost() must replace updatedAt with a new DateTimeImmutable instance',
        );
        self::assertNotNull($item->getUpdatedAt(), 'updatedAt must not be null after updateQuantityAndCost()');
    }

    public function testUpdateQuantityAndCostDoesNotChangeCreatedAt(): void
    {
        $createdAt = new \DateTimeImmutable('2020-06-15T12:00:00+00:00');
        $item = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('5.00'),
            totalCost: Cost::fromString('5.00'),
            quantity: 1,
            createdAt: $createdAt,
            updatedAt: new \DateTimeImmutable(),
        );

        $item->updateQuantityAndCost(10, new Price('5.00'), Cost::fromString('50.00'));

        self::assertSame(
            $createdAt,
            $item->getCreatedAt(),
            'updateQuantityAndCost() must never modify createdAt',
        );
    }

    public function testUpdateQuantityAndCostReturnsFluentSelf(): void
    {
        $item = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('1.00'),
            totalCost: Cost::fromString('1.00'),
            quantity: 1,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $result = $item->updateQuantityAndCost(2, new Price('1.00'), Cost::fromString('2.00'));

        self::assertSame($item, $result, 'updateQuantityAndCost() must return the same CartItem instance (fluent interface)');
    }

    /**
     * @return array<string, array{int, string, string}>
     */
    public static function quantityAndCostProvider(): array
    {
        return [
            'single unit' => [1, '99.99', '99.99'],
            'two units' => [2, '50.00', '100.00'],
            'ten units' => [10, '9.99', '99.90'],
            'large quantity' => [100, '1.50', '150.00'],
        ];
    }

    /**
     * @dataProvider quantityAndCostProvider
     */
    public function testUpdateQuantityAndCostAcceptsVariousQuantityAndCostCombinations(
        int $quantity,
        string $pricePerItem,
        string $totalCost,
    ): void {
        $item = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('0.00'),
            totalCost: Cost::zero(),
            quantity: 1,
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
        );

        $item->updateQuantityAndCost($quantity, new Price($pricePerItem), Cost::fromString($totalCost));

        self::assertSame($quantity, $item->getQuantity(), sprintf('Quantity must be set to %d', $quantity));
        self::assertSame(
            $pricePerItem,
            $item->getPerItemPrice()->getPrice(),
            sprintf('perItemPrice must be set to %s', $pricePerItem),
        );
        self::assertSame(
            $totalCost,
            $item->getTotalCost()->getCost(),
            sprintf('totalCost must be set to %s', $totalCost),
        );
    }
}

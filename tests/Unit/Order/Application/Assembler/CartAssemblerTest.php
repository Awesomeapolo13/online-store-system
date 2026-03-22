<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Application\Assembler;

use App\Order\Application\Assembler\CartAssembler;
use App\Order\Application\Response\CartItemResponse;
use App\Order\Application\Response\CartResponse;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\OrderDate;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use App\Order\Domain\ValueObject\Type;
use PHPUnit\Framework\TestCase;

final class CartAssemblerTest extends TestCase
{
    private CartAssembler $assembler;

    protected function setUp(): void
    {
        $this->assembler = new CartAssembler();
    }

    public function testToResponseWithEmptyCart(): void
    {
        $region = new Region(52);
        $type = new Type(isDelivery: false, isExpress: true);
        $orderDate = OrderDate::createDefault();
        $createdAt = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-01-01T10:00:00+00:00');
        $totalCost = Cost::zero();

        $cart = new Cart(
            region: $region,
            type: $type,
            orderDate: $orderDate,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            totalCost: $totalCost,
            userId: 42,
            shopNum: 7,
        );
        $cart->setId(1);

        $response = $this->assembler->toResponse($cart);

        self::assertInstanceOf(CartResponse::class, $response, 'Response should be an instance of CartResponse');
        self::assertSame(1, $response->id, 'Cart ID should match');
        self::assertSame(42, $response->userId, 'User ID should match');
        self::assertSame(7, $response->shopNum, 'Shop number should match');
        self::assertSame(52, $response->region, 'Region code should match');
        self::assertFalse($response->isDelivery, 'isDelivery should be false');
        self::assertTrue($response->isExpress, 'isExpress should be true');
        self::assertSame('0.00', $response->totalCost, 'Total cost should be zero');
        self::assertSame(
            $orderDate->getOrderDate()->format(\DateTimeInterface::RFC3339),
            $response->orderDate,
            'Order date should be formatted as RFC3339',
        );
        self::assertEmpty($response->cartItems, 'cartItems should be empty for a cart with no items');
    }

    public function testToResponseWithCartItems(): void
    {
        $region = new Region(77);
        $type = new Type(isDelivery: true, isExpress: false);
        $orderDate = OrderDate::createDefault();
        $createdAt = new \DateTimeImmutable('2026-02-15T08:00:00+00:00');
        $updatedAt = new \DateTimeImmutable('2026-02-15T08:30:00+00:00');
        $totalCost = Cost::fromString('199.99');

        $cart = new Cart(
            region: $region,
            type: $type,
            orderDate: $orderDate,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
            totalCost: $totalCost,
            userId: 99,
            shopNum: null,
        );
        $cart->setId(10);

        $cartItem = new CartItem(
            supCode: 'SUP-001',
            perItemPrice: new Price('49.99'),
            totalCost: Cost::fromString('199.96'),
            quantity: 4,
            createdAt: $createdAt,
            updatedAt: $updatedAt,
        );
        $cartItem->setId(55);
        $cartItem->setCart($cart);

        // Add item to cart's collection via the real collection on the cart
        $cart->getCartItems()->add($cartItem);

        $response = $this->assembler->toResponse($cart);

        self::assertInstanceOf(CartResponse::class, $response, 'Response should be an instance of CartResponse');
        self::assertSame(10, $response->id, 'Cart ID should match');
        self::assertSame(99, $response->userId, 'User ID should match');
        self::assertNull($response->shopNum, 'Shop number should be null');
        self::assertSame(77, $response->region, 'Region code should match');
        self::assertTrue($response->isDelivery, 'isDelivery should be true');
        self::assertFalse($response->isExpress, 'isExpress should be false');
        self::assertSame('199.99', $response->totalCost, 'Total cost should match');
        self::assertCount(1, $response->cartItems, 'There should be exactly one cart item');

        $itemResponse = $response->cartItems[0];
        self::assertInstanceOf(CartItemResponse::class, $itemResponse, 'Item should be an instance of CartItemResponse');
        self::assertSame(55, $itemResponse->id, 'CartItem ID should match');
        self::assertSame('SUP-001', $itemResponse->supCode, 'Supplier code should match');
        self::assertSame('49.99', $itemResponse->perItemPrice, 'Per-item price should match');
        self::assertSame('199.96', $itemResponse->totalCost, 'CartItem total cost should match');
        self::assertSame(4, $itemResponse->quantity, 'Quantity should match');
    }
}

<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\Entity;

use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\OrderDate;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use App\Order\Domain\ValueObject\Type;
use PHPUnit\Framework\TestCase;

final class CartTest extends TestCase
{
    // -------------------------------------------------------------------------
    // addCartItem
    // -------------------------------------------------------------------------

    public function testAddCartItemAddsItemToCollectionAndSetsBackReference(): void
    {
        $cart = $this->makeCart();
        $item = $this->makeCartItem('123456789012345', '10.00', 1);

        $cart->addCartItem($item);

        self::assertCount(1, $cart->getCartItems(), 'Cart should contain exactly one item after addCartItem()');
        self::assertSame($item, $cart->getCartItems()->first(), 'The added item must be the one in the collection');
        self::assertSame($cart, $item->getCart(), 'addCartItem() must set the back-reference on CartItem');
    }

    public function testAddCartItemDoesNotAddDuplicateItems(): void
    {
        $cart = $this->makeCart();
        $item = $this->makeCartItem('123456789012345', '10.00', 1);

        // Add the same object instance twice.
        $cart->addCartItem($item);
        $cart->addCartItem($item);

        self::assertCount(
            1,
            $cart->getCartItems(),
            'addCartItem() must not add the same CartItem instance more than once',
        );
    }

    public function testAddCartItemDoesNotOverrideExistingBackReferenceWhenItemAlreadyPresent(): void
    {
        $cart = $this->makeCart();
        $item = $this->makeCartItem('123456789012345', '10.00', 1);

        $cart->addCartItem($item);
        // Call again with the same item — back-reference should remain stable.
        $cart->addCartItem($item);

        self::assertSame(
            $cart,
            $item->getCart(),
            'The back-reference must remain the original cart after a duplicate addCartItem() call',
        );
    }

    // -------------------------------------------------------------------------
    // recalculateTotalCost
    // -------------------------------------------------------------------------

    public function testRecalculateTotalCostReturnZeroForEmptyCart(): void
    {
        $cart = $this->makeCart();

        $cart->recalculateTotalCost();

        self::assertSame(
            '0.00',
            $cart->getTotalCost()->getCost(),
            'recalculateTotalCost() must produce 0.00 for a cart with no items',
        );
    }

    public function testRecalculateTotalCostSumsAllCartItemTotalCosts(): void
    {
        $cart = $this->makeCart();

        // Add items directly to the collection (bypassing addCartItem) to isolate recalculateTotalCost()
        // from the logic of addCartItem().
        $cart->getCartItems()->add($this->makeCartItem('111111111111111', '50.00', 3));  // totalCost = 150.00
        $cart->getCartItems()->add($this->makeCartItem('222222222222222', '25.50', 2));  // totalCost = 51.00

        $cart->recalculateTotalCost();

        self::assertSame(
            '201.00',
            $cart->getTotalCost()->getCost(),
            'recalculateTotalCost() must sum all CartItem totalCosts: 150.00 + 51.00 = 201.00',
        );
    }

    public function testRecalculateTotalCostOverwritesPreviousTotal(): void
    {
        // Cart starts with a non-zero totalCost.
        $cart = $this->makeCart(totalCost: Cost::fromString('999.00'));

        // No items — recalculation must reset to 0.00.
        $cart->recalculateTotalCost();

        self::assertSame(
            '0.00',
            $cart->getTotalCost()->getCost(),
            'recalculateTotalCost() must overwrite the existing totalCost even when the result is 0.00',
        );
    }

    public function testRecalculateTotalCostHandlesMultipleItems(): void
    {
        $cart = $this->makeCart();

        $cart->getCartItems()->add($this->makeCartItem('111111111111111', '10.00', 1));  // 10.00
        $cart->getCartItems()->add($this->makeCartItem('222222222222222', '20.00', 2));  // 40.00
        $cart->getCartItems()->add($this->makeCartItem('333333333333333', '5.50', 4));   // 22.00

        $cart->recalculateTotalCost();

        self::assertSame(
            '72.00',
            $cart->getTotalCost()->getCost(),
            'recalculateTotalCost() must correctly sum 10.00 + 40.00 + 22.00 = 72.00',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function makeCart(?Cost $totalCost = null): Cart
    {
        return new Cart(
            region: new Region(52),
            type: new Type(isDelivery: false, isExpress: false),
            orderDate: OrderDate::createDefault(),
            createdAt: new \DateTimeImmutable(),
            updatedAt: new \DateTimeImmutable(),
            totalCost: $totalCost ?? Cost::zero(),
            userId: 1,
            shopNum: null,
        );
    }

    /**
     * Creates a CartItem with totalCost = perItemPrice × quantity.
     */
    private function makeCartItem(string $supCode, string $perItemPrice, int $quantity): CartItem
    {
        $now = new \DateTimeImmutable();
        $totalCost = Cost::fromString(bcmul($perItemPrice, (string) $quantity, 2));

        return new CartItem(
            supCode: $supCode,
            perItemPrice: new Price($perItemPrice),
            totalCost: $totalCost,
            quantity: $quantity,
            createdAt: $now,
            updatedAt: $now,
        );
    }
}

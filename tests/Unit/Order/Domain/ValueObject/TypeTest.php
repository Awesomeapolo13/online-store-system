<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\ValueObject\Type;
use PHPUnit\Framework\TestCase;

final class TypeTest extends TestCase
{
    public function testCreateIsExpressWhenOrderDateIsBeforeTomorrow15h(): void
    {
        $orderDate = (new \DateTimeImmutable('tomorrow'))->setTime(10, 0);

        $type = Type::create(false, $orderDate);

        self::assertTrue($type->isExpress(), 'Order date before tomorrow 15:00 must produce isExpress=true');
    }

    public function testCreateIsNotExpressWhenOrderDateIsAfterTomorrow15h(): void
    {
        $orderDate = (new \DateTimeImmutable('tomorrow'))->setTime(20, 0);

        $type = Type::create(false, $orderDate);

        self::assertFalse($type->isExpress(), 'Order date after tomorrow 15:00 must produce isExpress=false');
    }

    public function testCreateWithDeliveryAndExpressDateProducesExpressDelivery(): void
    {
        $orderDate = (new \DateTimeImmutable('tomorrow'))->setTime(10, 0);

        $type = Type::create(true, $orderDate);

        self::assertTrue($type->isDelivery(), 'isDelivery must be true when created with isDelivery=true');
        self::assertTrue($type->isExpress(), 'Express date must produce isExpress=true');
        self::assertTrue($type->isExpressDelivery(), 'Express + delivery = isExpressDelivery()');
        self::assertFalse($type->isPreDelivery(), 'Express delivery must not be pre-delivery');
    }

    public function testCreateWithDeliveryAndPreDateProducesPreDelivery(): void
    {
        $orderDate = (new \DateTimeImmutable('tomorrow'))->setTime(20, 0);

        $type = Type::create(true, $orderDate);

        self::assertTrue($type->isDelivery(), 'isDelivery must be true when created with isDelivery=true');
        self::assertFalse($type->isExpress(), 'Late date must produce isExpress=false');
        self::assertTrue($type->isPreDelivery(), 'Non-express delivery = isPreDelivery()');
        self::assertFalse($type->isExpressDelivery(), 'Pre-delivery must not be express delivery');
    }

    public function testCreateWithPickupProducesNonDelivery(): void
    {
        $orderDate = (new \DateTimeImmutable('tomorrow'))->setTime(10, 0);

        $type = Type::create(false, $orderDate);

        self::assertFalse($type->isDelivery(), 'isDelivery must be false when created with isDelivery=false');
        self::assertTrue($type->isExpressPickUp(), 'Express date + pickup = isExpressPickUp()');
        self::assertFalse($type->isPrePickUp(), 'Express pickup must not be pre-pickup');
    }

    public function testDefaultIsExpressPickup(): void
    {
        $type = Type::default();

        self::assertTrue($type->isExpress(), 'Default type must be express');
        self::assertFalse($type->isDelivery(), 'Default type must not be delivery (pickup)');
        self::assertTrue($type->isExpressPickUp(), 'Default type must be express pickup');
    }
}

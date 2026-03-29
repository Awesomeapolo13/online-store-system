<?php

declare(strict_types=1);

namespace App\Tests\Unit\Order\Domain\ValueObject;

use App\Order\Domain\ValueObject\OrderDate;
use PHPUnit\Framework\TestCase;

final class OrderDateTest extends TestCase
{
    public function testCreateThrowsWhenDateIsLessThanTwoHoursFromNow(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrderDate::create(new \DateTimeImmutable('+1 hour'));
    }

    public function testCreateThrowsWhenDateIsInThePast(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        OrderDate::create(new \DateTimeImmutable('yesterday'));
    }

    public function testCreateSucceedsWhenDateIsAtLeastTwoHoursFromNow(): void
    {
        $input = new \DateTimeImmutable('+3 hours');

        $orderDate = OrderDate::create($input);

        self::assertSame(
            $input->getTimestamp(),
            $orderDate->getOrderDate()->getTimestamp(),
            'getOrderDate() must return the exact date that was passed to create()',
        );
    }

    public function testCreateDefaultReturnsDateTimeImmutable(): void
    {
        $orderDate = OrderDate::createDefault();

        self::assertInstanceOf(
            \DateTimeImmutable::class,
            $orderDate->getOrderDate(),
            'createDefault() must return a DateTimeImmutable instance',
        );
    }
}

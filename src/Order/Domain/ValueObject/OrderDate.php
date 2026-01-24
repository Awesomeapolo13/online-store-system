<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

readonly class OrderDate
{
    private function __construct(
        private \DateTimeImmutable $orderDate,
    ) {
    }

    public function getOrderDate(): \DateTimeImmutable
    {
        return $this->orderDate;
    }

    public static function create(\DateTimeImmutable $orderDate): self
    {
        $currentDate = new \DateTimeImmutable();

        if ($currentDate > $orderDate) {
            throw new \InvalidArgumentException('Order date cannot be before current date ' . $orderDate->format(\DateTimeInterface::RFC3339));
        }

        return new self($orderDate);
    }
}

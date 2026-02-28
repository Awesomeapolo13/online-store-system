<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

readonly class Price
{
    public function __construct(
        private string $price,
    ) {
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    public function getPrice(): string
    {
        return $this->price;
    }

    public function isZero(): bool
    {
        return $this->price === '0.00';
    }
}

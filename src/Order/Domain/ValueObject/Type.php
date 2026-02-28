<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

readonly class Type
{
    public function __construct(
        protected bool $isDelivery,
        protected bool $isExpress,
    ) {
    }

    public function isDelivery(): bool
    {
        return $this->isDelivery;
    }

    public function isExpress(): bool
    {
        return $this->isExpress;
    }

    public function isExpressDelivery(): bool
    {
        return $this->isExpress && $this->isDelivery;
    }

    public function isExpressPickUp(): bool
    {
        return $this->isExpress && !$this->isDelivery;
    }

    public function isPreDelivery(): bool
    {
        return !$this->isExpress && $this->isDelivery;
    }

    public function isPrePickUp(): bool
    {
        return !$this->isExpress && !$this->isDelivery;
    }

    public static function default(): self
    {
        return new self(false, true);
    }

    public static function create(bool $isDelivery, \DateTimeInterface $orderDate): self
    {
        $isExpress = $orderDate <= (new \DateTime('tomorrow'))->setTime(15, 0);

        return new Type($isDelivery, $isExpress);
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

readonly class Cost
{
    private const RESULT_SCALE = 2;

    public function __construct(
        private string $cost,
    ) {
        $this->assertCost();
    }

    public static function zero(): self
    {
        return new self('0.00');
    }

    public static function fromString(string $cost): self
    {
        if ('' === $cost) {
            return self::zero();
        }

        $formattedCost = number_format((float) $cost, 2, '.', '');

        return new self($formattedCost);
    }

    public function getCost(): string
    {
        return $this->cost;
    }

    public function add(Cost $cost): self
    {
        $newCost = bcadd($this->cost, $cost->getCost(), self::RESULT_SCALE);

        return self::fromString($newCost);
    }

    public function subtract(Cost $cost): self
    {
        if ($this->lessThan($cost)) {
            return self::zero();
        }
        $newCost = bcsub($this->cost, $cost->getCost(), self::RESULT_SCALE);

        return self::fromString($newCost);
    }

    public function equals(Cost $cost): bool
    {
        return bccomp($this->cost, $cost->getCost(), self::RESULT_SCALE) === 0;
    }

    public function moreThan(Cost $cost): bool
    {
        return bccomp($this->cost, $cost->getCost(), self::RESULT_SCALE) === 1;
    }

    public function lessThan(Cost $cost): bool
    {
        return bccomp($this->cost, $cost->getCost(), self::RESULT_SCALE) === -1;
    }

    private function assertCost(): void
    {
        if (!preg_match('/^\d+\.\d{2}$/', $this->cost)) {
            throw new \InvalidArgumentException('Cost must be in format "0.00", got: ' . $this->cost);
        }
    }
}

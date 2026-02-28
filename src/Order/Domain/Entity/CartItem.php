<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;

class CartItem
{
    private ?int $id = null;

    private ?Cart $cart = null;

    public function __construct(
        private string $supCode,
        private Price $perItemPrice,
        private Cost $totalCost,
        private int $quantity,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
    ) {
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): self
    {
        $this->cart = $cart;

        return $this;
    }

    public function getSupCode(): string
    {
        return $this->supCode;
    }

    public function setSupCode(string $supCode): self
    {
        $this->supCode = $supCode;

        return $this;
    }

    public function getPerItemPrice(): Price
    {
        return $this->perItemPrice;
    }

    public function setPerItemPrice(Price $perItemPrice): self
    {
        $this->perItemPrice = $perItemPrice;

        return $this;
    }

    public function getTotalCost(): Cost
    {
        return $this->totalCost;
    }

    public function setTotalCost(Cost $totalCost): self
    {
        $this->totalCost = $totalCost;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): CartItem
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

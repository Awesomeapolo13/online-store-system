<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;

class OrderItem
{
    private ?int $id = null;

    private ?Order $order = null;

    public function __construct(
        private string $supCode,
        private Price $perItemPrice,
        private Cost $totalCost,
        private Cost $actualTotalCost,
        private int $quantity,
        private int $actualQuantity,
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

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): self
    {
        $this->order = $order;

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

    public function getActualTotalCost(): Cost
    {
        return $this->actualTotalCost;
    }

    public function setActualTotalCost(Cost $actualTotalCost): self
    {
        $this->actualTotalCost = $actualTotalCost;

        return $this;
    }

    public function getQuantity(): int
    {
        return $this->quantity;
    }

    public function getActualQuantity(): int
    {
        return $this->actualQuantity;
    }

    public function updateQuantityAndCost(int $quantity, Price $perItemPrice, Cost $totalCost): self
    {
        $this->quantity = $quantity;
        $this->perItemPrice = $perItemPrice;
        $this->totalCost = $totalCost;
        $this->updatedAt = new \DateTimeImmutable();

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\OrderDate;
use App\Order\Domain\ValueObject\Region;
use App\Order\Domain\ValueObject\Type;
use App\Shared\Domain\Event\EventInterface;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

class Cart
{
    private ?int $id = null;
    private int $version = 1;
    private ?\DateTimeImmutable $deletedAt = null;
    /**
     * @var Collection<CartItem>
     */
    private Collection $cartItems {
        get {
            return $this->cartItems;
        }
    }
    private Collection $domainEvents;

    public function __construct(
        private Region $region,
        private Type $type,
        private OrderDate $orderDate,
        private readonly ?\DateTimeImmutable $createdAt,
        private ?\DateTimeImmutable $updatedAt,
        private Cost $totalCost,
        private ?int $userId = null,
        private ?int $shopNum = null,
    ) {
        $this->cartItems = new ArrayCollection();
        $this->initializeDomainEvents();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(?int $id): void
    {
        $this->id = $id;
    }

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getShopNum(): ?int
    {
        return $this->shopNum;
    }

    public function setShopNum(?int $shopNum): self
    {
        $this->shopNum = $shopNum;

        return $this;
    }

    public function getRegion(): Region
    {
        return $this->region;
    }

    public function setRegion(Region $region): self
    {
        $this->region = $region;

        return $this;
    }

    public function getType(): Type
    {
        return $this->type;
    }

    public function setType(Type $type): self
    {
        $this->type = $type;

        return $this;
    }

    public function getOrderDate(): OrderDate
    {
        return $this->orderDate;
    }

    public function setOrderDate(OrderDate $orderDate): self
    {
        $this->orderDate = $orderDate;

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

    public function getTotalCost(): Cost
    {
        return $this->totalCost;
    }

    public function setTotalCost(Cost $totalCost): self
    {
        $this->totalCost = $totalCost;

        return $this;
    }

    public function getVersion(): int
    {
        return $this->version;
    }

    public function setVersion(int $version): self
    {
        $this->version = $version;

        return $this;
    }

    public function findItemBySupCode(string $supCode): ?CartItem
    {
        return $this->cartItems->findFirst(static fn (CartItem $cartItem) => $cartItem->getSupCode() === $supCode);
    }

    public function removeCartItem(CartItem $cartItem): self
    {
        if ($this->cartItems->removeElement($cartItem)) {
            $cartItem->setCart(null);
        }

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeImmutable $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }

    /**
     * @return EventInterface[]
     */
    public function releaseEvents(): array
    {
        $events = $this->domainEvents->toArray();
        $this->domainEvents->clear();

        return $events;
    }

    public function initializeDomainEvents(): void
    {
        $this->domainEvents = new ArrayCollection();
    }

    public function markAsDeleted(): void
    {
        $this->deletedAt = new \DateTimeImmutable();
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function updateTimestamps(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    protected function recordEvent(EventInterface $event): void
    {
        $this->domainEvents->add($event);
    }
}

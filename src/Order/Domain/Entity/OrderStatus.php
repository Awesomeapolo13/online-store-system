<?php

declare(strict_types=1);

namespace App\Order\Domain\Entity;

class OrderStatus
{
    private ?int $id = null;

    public function __construct(
        private string $title,
        private string $code,
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getCode(): string
    {
        return $this->code;
    }
}

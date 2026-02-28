<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

class CartAlreadyExistsException extends DomainException
{
    public static function forUser(int $userId): self
    {
        return new self(
            sprintf('Active cart already exists for user %d', $userId),
        );
    }
}

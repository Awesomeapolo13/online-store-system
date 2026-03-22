<?php

declare(strict_types=1);

namespace App\Order\Domain\Exception;

final class ProductNotAvailableForOrderException extends DomainException
{
    public static function forSupCode(string $supCode): self
    {
        return new self(sprintf('Product "%s" is not available for order.', $supCode));
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Entity\Cart;

interface CartRepositoryInterface
{
    public function save(Cart $cart): void;
}

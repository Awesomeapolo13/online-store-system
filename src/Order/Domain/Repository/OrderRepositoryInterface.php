<?php

declare(strict_types=1);

namespace App\Order\Domain\Repository;

use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderStatus;

interface OrderRepositoryInterface
{
    public function save(Order $order): void;

    public function findStatusByStatusIdAndIsDelivery(int $statusId, bool $isDelivery): ?OrderStatus;
}

<?php

declare(strict_types=1);

namespace App\Order\Application\Query\GetCart;

use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Shared\Application\Query\QueryHandlerInterface;

final readonly class GetCartHandler implements QueryHandlerInterface
{
    public function __construct(private CartRepositoryInterface $cartRepository)
    {
    }

    public function __invoke(GetCartQuery $query): ?Cart
    {
        return $this->cartRepository->findActiveByUserIdAndRegion(
            $query->userId,
            $query->regionCode,
        );
    }
}

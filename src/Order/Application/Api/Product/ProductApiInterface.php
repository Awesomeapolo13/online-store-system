<?php

declare(strict_types=1);

namespace App\Order\Application\Api\Product;

use App\Order\Application\Api\Product\Dto\GetProductRequest;
use App\Order\Application\Api\Product\Dto\GetProductResponse;
use App\Order\Application\Api\Product\Exception\ProductApiException;

interface ProductApiInterface
{
    /**
     * @throws ProductApiException
     */
    public function getProduct(GetProductRequest $request): GetProductResponse;
}

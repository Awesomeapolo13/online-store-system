<?php

declare(strict_types=1);

namespace App\Order\Infrastructure\Http\Api\Product;

use App\Order\Application\Api\Product\Dto\GetProductRequest;
use App\Order\Application\Api\Product\Dto\GetProductResponse;
use App\Order\Application\Api\Product\Exception\ProductApiException;
use App\Order\Application\Api\Product\ProductApiInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final readonly class SymfonyClientProductApi implements ProductApiInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
    ) {
    }

    public function getProduct(GetProductRequest $request): GetProductResponse
    {
        $response = $this->httpClient->request('GET', $this->baseUrl . '/v1/product', [
            'query' => [
                'sup_code' => $request->supCode,
                'shop_number' => $request->shopNumber,
                'region' => $request->region,
            ],
        ]);

        $data = $response->toArray(throw: false);

        if (!($data['success'] ?? false)) {
            $errorCode = (string) ($data['data']['error']['code'] ?? 'unknown');
            $errorMessage = (string) ($data['data']['error']['message'] ?? '');

            throw new ProductApiException($errorCode, $errorMessage);
        }

        $product = $data['data']['product'];

        return new GetProductResponse(
            supCode: (string) $product['sup_code'],
            pricePerItem: (string) $product['price_per_item'],
            isAvailableForOrder: (bool) $product['is_available_for_order'],
        );
    }
}

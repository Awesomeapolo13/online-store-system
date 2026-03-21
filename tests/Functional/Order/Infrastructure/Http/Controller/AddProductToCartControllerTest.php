<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Infrastructure\Http\Controller;

use App\Order\Application\Api\Product\Dto\GetProductResponse;
use App\Order\Application\Api\Product\Exception\ProductApiException;
use App\Order\Application\Api\Product\ProductApiInterface;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class AddProductToCartControllerTest extends WebTestCase
{
    /**
     * Dedicated user ID for the "no cart exists" scenario.
     * CreateNewCartCommand is dispatched and a DomainException is returned.
     */
    private const int USER_ID_NO_CART = 301;

    /** Dedicated user ID for the "add product successfully" scenario. */
    private const int USER_ID_SUCCESS = 302;

    /** Dedicated user ID for the "product already in cart with same quantity (no-op)" scenario. */
    private const int USER_ID_SAME_QTY = 303;

    /** Dedicated user ID for the "product already in cart with different quantity" scenario. */
    private const int USER_ID_DIFF_QTY = 304;

    /** Dedicated user ID for the "ProductApiException code 1001 — item removed" scenario. */
    private const int USER_ID_API_ERROR = 305;

    /** Dedicated user ID for the "product not available for order" scenario. */
    private const int USER_ID_NOT_AVAILABLE = 306;

    private const string SUP_CODE = '369428000000000';
    private const string ORDER_DATE = '2026-06-26T13:00:00.000+03:00';
    private const int REGION = RegionCodeEnum::NIZHNY_NOVGOROD->value;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        $this->client = self::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Remove any leftover data from a previous failed run before creating fresh test data.
        // CartItems must be deleted first due to the FK constraint.
        $this->cleanUpTestData();

        $createCartHandler = $container->get(CreateNewCartHandler::class);

        // Pre-create carts for every scenario that needs an existing cart.
        foreach ([
            self::USER_ID_SUCCESS,
            self::USER_ID_SAME_QTY,
            self::USER_ID_DIFF_QTY,
            self::USER_ID_API_ERROR,
            self::USER_ID_NOT_AVAILABLE,
        ] as $userId) {
            $createCartHandler(new CreateNewCartCommand(
                userId: $userId,
                region: new Region(self::REGION),
            ));
        }

        // Seed cart items DIRECTLY via repository (no AddProductToCartHandler, no ProductApiInterface).
        // This ensures ProductApiInterface is NOT initialized in setUp(), so each test can inject its own mock.
        $cartRepository = $container->get(CartRepositoryInterface::class);

        // Переделать на фикстуры.
        foreach ([self::USER_ID_SAME_QTY => 4, self::USER_ID_DIFF_QTY => 2, self::USER_ID_API_ERROR => 4] as $userId => $qty) {
            $cart = $cartRepository->findActiveByUserIdAndRegion($userId, self::REGION);
            $price = new Price('100.00');
            $itemCost = Cost::fromString(bcmul('100.00', (string) $qty, 2));
            $now = new \DateTimeImmutable();
            $item = new CartItem(
                supCode: self::SUP_CODE,
                perItemPrice: $price,
                totalCost: $itemCost,
                quantity: $qty,
                createdAt: $now,
                updatedAt: $now,
            );
            $cart->addCartItem($item);
            $cart->recalculateTotalCost();
            $cartRepository->save($cart);
        }
    }

    protected function tearDown(): void
    {
        // Clean up BEFORE parent::tearDown(), which shuts the kernel and closes the EntityManager.
        $this->cleanUpTestData();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Scenario a: no cart → CreateNewCartCommand dispatched, 400 returned
    // -------------------------------------------------------------------------
    public function testReturns400WithRetryMessageWhenCartDoesNotExistYet(): void
    {
        static::getContainer()->set(ProductApiInterface::class, $this->createStub(ProductApiInterface::class));

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_NO_CART,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'Expected HTTP 400 when no cart exists and CreateNewCartCommand has been dispatched',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $data, 'Response must contain a "message" key');
        self::assertStringContainsString(
            'retry',
            strtolower($data['message']),
            'Error message should advise the caller to retry',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario b: new product added successfully
    // -------------------------------------------------------------------------
    public function testReturns200WithCartWhenProductIsAddedSuccessfully(): void
    {
        $mock = $this->createMock(ProductApiInterface::class);
        $mock->expects($this->once())
            ->method('getProduct')
            ->willReturn(new GetProductResponse(
                supCode: self::SUP_CODE,
                pricePerItem: '238.80',
                isAvailableForOrder: true,
            ))
        ;
        static::getContainer()->set(ProductApiInterface::class, $mock);

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_SUCCESS,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when product is added to cart successfully',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');

        $cart = $data['cart'];
        self::assertArrayHasKey('cartItems', $cart, 'Cart must contain "cartItems"');
        self::assertCount(1, $cart['cartItems'], 'Cart should contain exactly one item after adding');

        $item = $cart['cartItems'][0];
        self::assertSame(self::SUP_CODE, $item['supCode'], 'CartItem supCode must match the requested sup_code');
        self::assertSame(4, $item['quantity'], 'CartItem quantity must match the requested quantity');
        self::assertSame('238.80', $item['perItemPrice'], 'CartItem perItemPrice must match the mocked API price');
        self::assertSame('955.20', $item['totalCost'], 'CartItem totalCost must be 238.80 × 4 = 955.20');
        self::assertSame('955.20', $cart['totalCost'], 'Cart totalCost must be 955.20 after recalculation');
    }

    // -------------------------------------------------------------------------
    // Scenario c: product already in cart with SAME quantity — no-op, 200 returned
    // -------------------------------------------------------------------------
    public function testReturns200WithUnchangedCartWhenProductAlreadyInCartWithSameQuantity(): void
    {
        // Cart was pre-seeded with SUP_CODE at quantity=4 and price=100.00
        $mock = $this->createMock(ProductApiInterface::class);
        $mock->expects($this->once())
            ->method('getProduct')
            ->willReturn(new GetProductResponse(
                supCode: self::SUP_CODE,
                pricePerItem: '100.00',
                isAvailableForOrder: true,
            ))
        ;
        static::getContainer()->set(ProductApiInterface::class, $mock);

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_SAME_QTY,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4, // same as pre-seeded
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 even when product is already in cart with the same quantity (no-op)',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertCount(1, $data['cart']['cartItems'], 'Cart should still have exactly one item after no-op');

        $item = $data['cart']['cartItems'][0];
        self::assertSame(4, $item['quantity'], 'Quantity must remain unchanged at 4');
        self::assertSame('400.00', $item['totalCost'], 'Total cost must remain 100.00 × 4 = 400.00');
    }

    // -------------------------------------------------------------------------
    // Scenario d: product already in cart with DIFFERENT quantity — quantity updated
    // -------------------------------------------------------------------------
    public function testReturns200WithUpdatedQuantityWhenProductAlreadyInCartWithDifferentQuantity(): void
    {
        // Cart was pre-seeded with SUP_CODE at quantity=2 and price=100.00
        $mock = $this->createMock(ProductApiInterface::class);
        $mock->expects($this->once())
            ->method('getProduct')
            ->willReturn(new GetProductResponse(
                supCode: self::SUP_CODE,
                pricePerItem: '100.00',
                isAvailableForOrder: true,
            ))
        ;
        static::getContainer()->set(ProductApiInterface::class, $mock);

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_DIFF_QTY,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 5, // different from pre-seeded quantity of 2
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when quantity is updated for an existing cart item',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertCount(1, $data['cart']['cartItems'], 'Cart should still contain exactly one item');

        $item = $data['cart']['cartItems'][0];
        self::assertSame(5, $item['quantity'], 'Quantity must be updated to 5');
        self::assertSame('500.00', $item['totalCost'], 'CartItem totalCost must be 100.00 × 5 = 500.00');
        self::assertSame('500.00', $data['cart']['totalCost'], 'Cart totalCost must be recalculated to 500.00');
    }

    // -------------------------------------------------------------------------
    // Scenario e: ProductApiException with error code 1001 — item removed, 400 returned
    // -------------------------------------------------------------------------

    public function testReturns400AndRemovesItemFromCartWhenProductApiReturnsErrorCode1001(): void
    {
        // Cart was pre-seeded with SUP_CODE at quantity=4
        $mock = $this->createMock(ProductApiInterface::class);
        $mock->expects($this->once())
            ->method('getProduct')
            ->willThrowException(new ProductApiException('1001', 'No such product'))
        ;
        static::getContainer()->set(ProductApiInterface::class, $mock);

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_API_ERROR,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'Expected HTTP 400 when ProductApiException with code 1001 is thrown',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $data, 'Response must contain a "message" key');
        self::assertSame('No such product', $data['message'], 'Error message must propagate from ProductApiException');

        // Re-fetch the cart to verify the item was removed from persistence.
        $cartRepository = static::getContainer()->get(CartRepositoryInterface::class);
        $cart = $cartRepository->findActiveByUserIdAndRegion(self::USER_ID_API_ERROR, self::REGION);

        self::assertNotNull($cart, 'Cart itself must still exist after item removal');
        self::assertCount(
            0,
            $cart->getCartItems(),
            'Cart must have no items after ProductApiException(1001) triggered item removal',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario f: product not available for order
    // -------------------------------------------------------------------------

    public function testReturns400WhenProductIsNotAvailableForOrder(): void
    {
        $mock = $this->createMock(ProductApiInterface::class);
        $mock->expects($this->once())
            ->method('getProduct')
            ->willReturn(new GetProductResponse(
                supCode: self::SUP_CODE,
                pricePerItem: '238.80',
                isAvailableForOrder: false,
            ))
        ;
        static::getContainer()->set(ProductApiInterface::class, $mock);

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_NOT_AVAILABLE,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'Expected HTTP 400 when the product API reports the product is not available for order',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $data, 'Response must contain a "message" key');
        self::assertStringContainsString(
            'not available',
            strtolower($data['message']),
            'Error message should indicate the product is not available for order',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario g: missing user_id field in the request body
    // -------------------------------------------------------------------------

    public function testReturns400WhenUserIdIsMissingFromRequestBody(): void
    {
        static::getContainer()->set(ProductApiInterface::class, $this->createStub(ProductApiInterface::class));

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                // user_id deliberately omitted
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when the user_id field is missing from the request body',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario h: invalid sup_code format
    // -------------------------------------------------------------------------

    public function testReturns400WhenSupCodeHasInvalidFormat(): void
    {
        static::getContainer()->set(ProductApiInterface::class, $this->createStub(ProductApiInterface::class));

        $this->client->request(
            'POST',
            '/api/v1/cart/add',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_SUCCESS,
                'region' => self::REGION,
                'sup_code' => 'invalid', // fails Regex('/^\d{15}$/')
                'quantity' => 4,
                'order_date' => self::ORDER_DATE,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when sup_code is not a 15-digit numeric string',
        );
    }

    private function cleanUpTestData(): void
    {
        $userIds = [
            self::USER_ID_NO_CART,
            self::USER_ID_SUCCESS,
            self::USER_ID_SAME_QTY,
            self::USER_ID_DIFF_QTY,
            self::USER_ID_API_ERROR,
            self::USER_ID_NOT_AVAILABLE,
        ];

        // Delete CartItems first to avoid FK constraint violation when deleting Carts.
        $this->entityManager
            ->createQuery(
                'DELETE FROM ' . CartItem::class . ' ci WHERE ci.cart IN '
                . '(SELECT c FROM ' . Cart::class . ' c WHERE c.userId IN (:userIds))',
            )
            ->setParameter('userIds', $userIds)
            ->execute()
        ;

        $this->entityManager
            ->createQuery('DELETE FROM ' . Cart::class . ' c WHERE c.userId IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->execute()
        ;
    }
}

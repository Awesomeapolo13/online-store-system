<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Infrastructure\Http\Controller;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CartSetUpControllerTest extends WebTestCase
{
    /** Dedicated user ID for the "no cart exists" scenario. */
    private const int USER_NO_CART = 701;

    /** Dedicated user ID for the delivery setup scenario. */
    private const int USER_WITH_CART = 702;

    /** Dedicated user ID for the pickup setup scenario. */
    private const int USER_WITH_CART2 = 703;

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
        $this->cleanUpTestData();

        $createCartHandler = $container->get(CreateNewCartHandler::class);

        foreach ([self::USER_WITH_CART, self::USER_WITH_CART2] as $userId) {
            $createCartHandler(new CreateNewCartCommand(
                userId: $userId,
                region: new Region(self::REGION),
            ));
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

    public function testReturns400WithRetryMessageWhenCartDoesNotExist(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_NO_CART,
                'region' => self::REGION,
                'order_date' => (new \DateTimeImmutable('+3 hours'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => true,
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
    // Scenario b: valid cart, is_delivery=true → 200, cart.isDelivery=true
    // -------------------------------------------------------------------------

    public function testReturns200WhenSettingDeliveryType(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART,
                'region' => self::REGION,
                'order_date' => (new \DateTimeImmutable('+3 hours'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => true,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when setting delivery type on an existing cart',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertTrue(
            $data['cart']['isDelivery'],
            'cart.isDelivery must be true after setting is_delivery=true',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario c: valid cart, is_delivery=false, shop_id=333 → 200, cart.shopNum=333
    // -------------------------------------------------------------------------

    public function testReturns200WhenSettingPickupTypeWithShopId(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART2,
                'region' => self::REGION,
                'order_date' => (new \DateTimeImmutable('+3 hours'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => false,
                'shop_id' => 333,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when setting pickup type with a shop_id on an existing cart',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertFalse(
            $data['cart']['isDelivery'],
            'cart.isDelivery must be false after setting is_delivery=false',
        );
        self::assertSame(333, $data['cart']['shopNum'], 'cart.shopNum must equal the shop_id passed in the request');
    }

    // -------------------------------------------------------------------------
    // Scenario d: is_delivery=false without shop_id → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenShopIdMissingForPickup(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART,
                'region' => self::REGION,
                'order_date' => (new \DateTimeImmutable('+3 hours'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => false,
                // shop_id deliberately omitted
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when is_delivery=false and shop_id is missing',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario e: invalid region → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenRegionIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART,
                'region' => 9999,
                'order_date' => (new \DateTimeImmutable('+3 hours'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => true,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when region is not a valid RegionCodeEnum value',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario f: missing order_date → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenOrderDateIsMissing(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART,
                'region' => self::REGION,
                // order_date deliberately omitted
                'is_delivery' => true,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when order_date is missing from the request body',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario g: order_date in the past → 400 (InvalidArgumentException from OrderDate::create)
    // -------------------------------------------------------------------------

    public function testReturns400WhenOrderDateIsInThePast(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/setup',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_WITH_CART,
                'region' => self::REGION,
                'order_date' => (new \DateTimeImmutable('yesterday'))->format(\DateTimeInterface::RFC3339),
                'is_delivery' => true,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when order_date is in the past (fails OrderDate::create() validation)',
        );
    }

    private function cleanUpTestData(): void
    {
        $userIds = [
            self::USER_NO_CART,
            self::USER_WITH_CART,
            self::USER_WITH_CART2,
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

<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Infrastructure\Http\Controller;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Entity\Order;
use App\Order\Domain\Entity\OrderItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class CreateOrderControllerTest extends WebTestCase
{
    /** Dedicated user ID: no cart at all. */
    private const int USER_NO_CART = 801;

    /** Dedicated user ID: has a cart but no items. */
    private const int USER_CART_EMPTY = 802;

    /** Dedicated user ID: has a cart with items — happy path. */
    private const int USER_CART_WITH_ITEMS = 803;

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
        $cartRepository = $container->get(CartRepositoryInterface::class);

        // Create an empty cart for user 802 — used to test "cart has no items" scenario.
        $createCartHandler(new CreateNewCartCommand(
            userId: self::USER_CART_EMPTY,
            region: new Region(self::REGION),
        ));

        // Create a cart for user 803 and seed it with one item — used for the happy path.
        $createCartHandler(new CreateNewCartCommand(
            userId: self::USER_CART_WITH_ITEMS,
            region: new Region(self::REGION),
        ));

        $cart = $cartRepository->findActiveByUserIdAndRegion(self::USER_CART_WITH_ITEMS, self::REGION);
        self::assertNotNull($cart, 'Cart for USER_CART_WITH_ITEMS must exist after creation');

        $now = new \DateTimeImmutable();
        $cartItem = new CartItem(
            supCode: '123456789012345',
            perItemPrice: new Price('10.00'),
            totalCost: Cost::fromString('10.00'),
            quantity: 1,
            createdAt: $now,
            updatedAt: $now,
        );

        $cart->addCartItem($cartItem);
        $cart->recalculateTotalCost();
        $cartRepository->save($cart);
    }

    protected function tearDown(): void
    {
        // Clean up BEFORE parent::tearDown(), which shuts the kernel and closes the EntityManager.
        $this->cleanUpTestData();

        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // Scenario 1: cart with items → HTTP 200
    // -------------------------------------------------------------------------

    public function testReturns200WhenOrderCreatedSuccessfully(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_CART_WITH_ITEMS,
                'region' => self::REGION,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when a valid cart with items exists',
        );

        // Symfony 7 JsonResponse(null) serialises to "{}" — just verify the status code.
        self::assertNotEmpty($response->getContent());
    }

    // -------------------------------------------------------------------------
    // Scenario 2: cart exists but has no items → HTTP 400
    // -------------------------------------------------------------------------

    public function testReturns400WhenCartHasNoItems(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_CART_EMPTY,
                'region' => self::REGION,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'Expected HTTP 400 when the cart exists but contains no items',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $data, 'Response must contain a "message" key');
        self::assertStringContainsString(
            'продукты',
            $data['message'],
            'Error message should mention "продукты"',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario 3: no cart at all → HTTP 400
    // -------------------------------------------------------------------------

    public function testReturns400WhenCartDoesNotExist(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_NO_CART,
                'region' => self::REGION,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $response->getStatusCode(),
            'Expected HTTP 400 when no cart exists for the given user and region',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('message', $data, 'Response must contain a "message" key');
        self::assertStringContainsString(
            'продукты',
            $data['message'],
            'Error message should mention "продукты"',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario 4: missing user_id → HTTP 400 (validation)
    // -------------------------------------------------------------------------

    public function testReturns400WhenUserIdIsMissing(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'region' => self::REGION,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when user_id is missing from the request body',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario 5: invalid region → HTTP 400 (validation)
    // -------------------------------------------------------------------------

    public function testReturns400WhenRegionIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/order',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_CART_WITH_ITEMS,
                'region' => 9999,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when region is not a valid RegionCodeEnum value',
        );
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function cleanUpTestData(): void
    {
        $userIds = [
            self::USER_NO_CART,
            self::USER_CART_EMPTY,
            self::USER_CART_WITH_ITEMS,
        ];

        // Delete OrderItems first to avoid FK constraint violation when deleting Orders.
        $this->entityManager
            ->createQuery(
                'DELETE FROM ' . OrderItem::class . ' oi WHERE oi.order IN '
                . '(SELECT o FROM ' . Order::class . ' o WHERE o.userId IN (:userIds))',
            )
            ->setParameter('userIds', $userIds)
            ->execute()
        ;

        $this->entityManager
            ->createQuery('DELETE FROM ' . Order::class . ' o WHERE o.userId IN (:userIds)')
            ->setParameter('userIds', $userIds)
            ->execute()
        ;

        // Delete CartItems before Carts.
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

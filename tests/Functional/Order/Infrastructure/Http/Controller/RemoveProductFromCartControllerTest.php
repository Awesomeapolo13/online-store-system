<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Infrastructure\Http\Controller;

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

final class RemoveProductFromCartControllerTest extends WebTestCase
{
    /** Dedicated user ID for the "no cart exists" scenario. */
    private const int USER_ID_NO_CART = 401;

    /** Dedicated user ID for the "product in cart — successfully removed" scenario. */
    private const int USER_ID_WITH_ITEM = 402;

    /** Dedicated user ID for the "product not in cart — no-op" scenario. */
    private const int USER_ID_WITHOUT_ITEM = 403;

    private const string SUP_CODE = '369428000000000';
    private const string OTHER_SUP_CODE = '111111111111111';
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

        foreach ([self::USER_ID_WITH_ITEM, self::USER_ID_WITHOUT_ITEM] as $userId) {
            $createCartHandler(new CreateNewCartCommand(
                userId: $userId,
                region: new Region(self::REGION),
            ));
        }

        // Seed a cart item for the "product in cart" scenario.
        $cartRepository = $container->get(CartRepositoryInterface::class);

        $cart = $cartRepository->findActiveByUserIdAndRegion(self::USER_ID_WITH_ITEM, self::REGION);
        $qty = 3;
        $price = new Price('50.00');
        $itemCost = Cost::fromString(bcmul('50.00', (string) $qty, 2));
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
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_NO_CART,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
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
    // Scenario b: product in cart → removed, totalCost recalculated, 200 returned
    // -------------------------------------------------------------------------

    public function testReturns200WithCartAndItemRemovedWhenProductExistsInCart(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_WITH_ITEM,
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when product is successfully removed from cart',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertCount(0, $data['cart']['cartItems'], 'Cart must have no items after removal');
        self::assertSame('0.00', $data['cart']['totalCost'], 'Cart totalCost must be 0.00 after removing the only item');
    }

    // -------------------------------------------------------------------------
    // Scenario c: product not in cart → no-op, cart returned unchanged, 200
    // -------------------------------------------------------------------------

    public function testReturns200WithUnchangedCartWhenProductIsNotInCart(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_WITHOUT_ITEM,
                'region' => self::REGION,
                'sup_code' => self::OTHER_SUP_CODE,
            ], \JSON_THROW_ON_ERROR),
        );

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 even when the product is not in the cart (idempotent no-op)',
        );

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response must contain a "cart" key');
        self::assertCount(0, $data['cart']['cartItems'], 'Cart must remain empty');
    }

    // -------------------------------------------------------------------------
    // Scenario d: missing user_id → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenUserIdIsMissing(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                // user_id deliberately omitted
                'region' => self::REGION,
                'sup_code' => self::SUP_CODE,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when user_id is missing from the request body',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario e: invalid region → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenRegionIsInvalid(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_WITH_ITEM,
                'region' => 9999,
                'sup_code' => self::SUP_CODE,
            ], \JSON_THROW_ON_ERROR),
        );

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when region is not a valid RegionCodeEnum value',
        );
    }

    // -------------------------------------------------------------------------
    // Scenario f: invalid sup_code format → 400 validation error
    // -------------------------------------------------------------------------

    public function testReturns400WhenSupCodeHasInvalidFormat(): void
    {
        $this->client->request(
            'POST',
            '/api/v1/cart/remove',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode([
                'user_id' => self::USER_ID_WITH_ITEM,
                'region' => self::REGION,
                'sup_code' => 'invalid',
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
            self::USER_ID_WITH_ITEM,
            self::USER_ID_WITHOUT_ITEM,
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

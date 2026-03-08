<?php

declare(strict_types=1);

namespace App\Tests\Functional\Order\Infrastructure\Http\Controller;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

final class GetCartControllerTest extends WebTestCase
{
    /**
     * Dedicated user ID for the "no cart exists" scenario.
     * Must not be used by any other test to avoid isolation issues.
     */
    private const int USER_ID_NO_CART = 201;

    /**
     * Dedicated user ID for the "cart exists" scenario.
     * A cart is pre-created for this user in setUp().
     */
    private const int USER_ID_WITH_CART = 202;

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();

        // createClient() boots the kernel exactly once; subsequent calls in the same test would throw.
        // All test methods must reuse $this->client instead of calling createClient() again.
        $this->client = self::createClient();

        $container = static::getContainer();
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Pre-create a cart for USER_ID_WITH_CART so the 200 scenario has data to retrieve.
        $handler = $container->get(CreateNewCartHandler::class);
        $handler(new CreateNewCartCommand(
            userId: self::USER_ID_WITH_CART,
            region: new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value),
        ));
    }

    protected function tearDown(): void
    {
        // Clean up test data BEFORE parent::tearDown(), which shuts the kernel down
        // and would make the EntityManager unavailable.
        $this
            ->entityManager
            ->createQuery('DELETE FROM ' . Cart::class . ' c WHERE c.userId IN (:userIds)')
            ->setParameter('userIds', [self::USER_ID_NO_CART, self::USER_ID_WITH_CART])
            ->execute();

        parent::tearDown();
    }

    public function testGetCartReturns202WhenNoCartExists(): void
    {
        $this->client->request('GET', '/api/v1/cart', [
            'user_id' => self::USER_ID_NO_CART,
            'region' => RegionCodeEnum::NIZHNY_NOVGOROD->value,
        ]);

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_ACCEPTED,
            $response->getStatusCode(),
            'Expected HTTP 202 when no cart exists for user',
        );
        // JsonResponse(null, 202) keeps the default body '{}' (Symfony does not call setData when null is passed)
        self::assertSame('{}', $response->getContent(), 'Response body should be empty JSON object for 202');
    }

    public function testGetCartReturns200WhenCartExists(): void
    {
        $this->client->request('GET', '/api/v1/cart', [
            'user_id' => self::USER_ID_WITH_CART,
            'region' => RegionCodeEnum::NIZHNY_NOVGOROD->value,
        ]);

        $response = $this->client->getResponse();

        self::assertSame(
            Response::HTTP_OK,
            $response->getStatusCode(),
            'Expected HTTP 200 when cart exists for user',
        );
        self::assertJson($response->getContent(), 'Response should be valid JSON');

        $data = json_decode($response->getContent(), true, 512, \JSON_THROW_ON_ERROR);

        self::assertArrayHasKey('cart', $data, 'Response JSON must contain a "cart" key');

        $cart = $data['cart'];
        self::assertArrayHasKey('id', $cart, 'Cart must have an "id" field');
        self::assertArrayHasKey('userId', $cart, 'Cart must have a "userId" field');
        self::assertArrayHasKey('region', $cart, 'Cart must have a "region" field');
        self::assertArrayHasKey('isDelivery', $cart, 'Cart must have an "isDelivery" field');
        self::assertArrayHasKey('isExpress', $cart, 'Cart must have an "isExpress" field');
        self::assertArrayHasKey('orderDate', $cart, 'Cart must have an "orderDate" field');
        self::assertArrayHasKey('totalCost', $cart, 'Cart must have a "totalCost" field');
        self::assertArrayHasKey('cartItems', $cart, 'Cart must have a "cartItems" field');
        self::assertIsArray($cart['cartItems'], 'cartItems must be an array');

        self::assertSame(self::USER_ID_WITH_CART, $cart['userId'], 'userId in response should match the requested user');
        self::assertSame(RegionCodeEnum::NIZHNY_NOVGOROD->value, $cart['region'], 'region in response should match the requested region');
    }

    public function testGetCartReturns400WhenUserIdMissing(): void
    {
        $this->client->request('GET', '/api/v1/cart', [
            'region' => RegionCodeEnum::NIZHNY_NOVGOROD->value,
        ]);

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when user_id query parameter is missing',
        );
    }

    public function testGetCartReturns400WhenRegionInvalid(): void
    {
        $this->client->request('GET', '/api/v1/cart', [
            'user_id' => 203,
            'region' => 999,
        ]);

        self::assertSame(
            Response::HTTP_BAD_REQUEST,
            $this->client->getResponse()->getStatusCode(),
            'Expected HTTP 400 when region is not a valid RegionCodeEnum value',
        );
    }
}

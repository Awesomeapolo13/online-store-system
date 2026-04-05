<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application\Command\AddProductToCart;

use App\Order\Application\Api\Product\Dto\GetProductResponse;
use App\Order\Application\Api\Product\ProductApiInterface;
use App\Order\Application\Command\AddProductToCart\AddProductToCartCommand;
use App\Order\Application\Command\AddProductToCart\AddProductToCartHandler;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class AddProductToCartHandlerTest extends KernelTestCase
{
    /** Dedicated user ID for the happy-path add-item scenario. */
    private const int USER_ID_ADD_ITEM = 701;

    /** Dedicated user ID for the optimistic lock scenario. */
    private const int USER_ID_LOCK = 702;

    private const int REGION = RegionCodeEnum::NIZHNY_NOVGOROD->value;

    private const string SUP_CODE = 'TEST-SUP-001';

    private CartRepositoryInterface $cartRepository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        // Do NOT get AddProductToCartHandler here — it would initialize ProductApiInterface
        // and prevent per-test mock injection. Resolve it fresh inside each test after setting the mock.
        $this->cartRepository = $container->get(CartRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Remove any leftover data from a previous failed run.
        $this->cleanUpTestData();

        $createCartHandler = $container->get(CreateNewCartHandler::class);
        $region = new Region(self::REGION);

        foreach ([self::USER_ID_ADD_ITEM, self::USER_ID_LOCK] as $userId) {
            $createCartHandler(new CreateNewCartCommand(userId: $userId, region: $region));
        }
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestData();

        parent::tearDown();
    }

    public function testAddsNewProductToCart(): void
    {
        $productApiMock = $this->createMock(ProductApiInterface::class);
        $productApiMock
            ->method('getProduct')
            ->willReturn(new GetProductResponse(
                supCode: self::SUP_CODE,
                pricePerItem: '150.00',
                isAvailableForOrder: true,
            ))
        ;

        self::getContainer()->set(ProductApiInterface::class, $productApiMock);

        $handler = self::getContainer()->get(AddProductToCartHandler::class);

        ($handler)(new AddProductToCartCommand(
            userId: self::USER_ID_ADD_ITEM,
            regionCode: self::REGION,
            supCode: self::SUP_CODE,
            quantity: 3,
        ));

        $this->entityManager->clear();

        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_ADD_ITEM, self::REGION);

        self::assertNotNull($cart, 'Cart must still exist after adding a product');
        self::assertCount(1, $cart->getCartItems(), 'Cart must contain exactly one item after add');

        $item = $cart->getCartItems()->first();
        self::assertInstanceOf(CartItem::class, $item);
        self::assertSame(self::SUP_CODE, $item->getSupCode(), 'Cart item supCode must match the command');
        self::assertSame(3, $item->getQuantity(), 'Cart item quantity must equal the command quantity');
        self::assertSame('150.00', $item->getPerItemPrice()->getPrice(), 'Per-item price must match the API response');
        self::assertSame('450.00', $item->getTotalCost()->getCost(), 'Item totalCost must equal perItemPrice * quantity');
        self::assertSame('450.00', $cart->getTotalCost()->getCost(), 'Cart totalCost must be recalculated after add');
    }

    public function testOptimisticLockThrowsWhenVersionIsStale(): void
    {
        // Fetch the cart that will become stale once we bump the DB version.
        $staleCart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_LOCK, self::REGION);
        self::assertNotNull($staleCart, 'Cart must exist for the lock test');

        // Bump the version in the DB directly so the in-memory cart is now stale.
        $this->entityManager
            ->createQuery(
                'UPDATE ' . Cart::class . ' c SET c.version = c.version + 1 WHERE c.userId = :userId',
            )
            ->setParameter('userId', self::USER_ID_LOCK)
            ->execute()
        ;

        // Register the optimistic lock against the stale (version = 1) entity.
        $this->cartRepository->lockOptimistic($staleCart);

        // Mark the entity dirty so Doctrine will attempt a flush.
        $staleCart->setShopNum(42);

        $this->expectException(\Doctrine\ORM\OptimisticLockException::class);

        $this->entityManager->flush();
    }

    public function testDoesNothingWhenCartNotFound(): void
    {
        $this->expectNotToPerformAssertions();

        $productApiStub = $this->createStub(ProductApiInterface::class);
        self::getContainer()->set(ProductApiInterface::class, $productApiStub);

        $handler = self::getContainer()->get(AddProductToCartHandler::class);

        // userId 99999 has no cart — handler must log a warning and return without throwing.
        ($handler)(new AddProductToCartCommand(
            userId: 99999,
            regionCode: self::REGION,
            supCode: self::SUP_CODE,
            quantity: 1,
        ));
    }

    private function cleanUpTestData(): void
    {
        $userIds = [self::USER_ID_ADD_ITEM, self::USER_ID_LOCK];

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
            ->execute();
    }
}

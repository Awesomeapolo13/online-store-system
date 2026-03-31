<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application\Command\CartSetUp;

use App\Order\Application\Command\CartSetUp\CartSetUpCommand;
use App\Order\Application\Command\CartSetUp\CartSetUpHandler;
use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CartSetUpHandlerTest extends KernelTestCase
{
    /** Dedicated user ID for the delivery-type scenario. */
    private const int USER_ID_DELIVERY = 601;

    /** Dedicated user ID for the pickup-type scenario. */
    private const int USER_ID_PICKUP = 602;

    private const int REGION = RegionCodeEnum::NIZHNY_NOVGOROD->value;

    private CartSetUpHandler $handler;
    private CartRepositoryInterface $cartRepository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->handler = $container->get(CartSetUpHandler::class);
        $this->cartRepository = $container->get(CartRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        // Remove any leftover data from a previous failed run.
        $this->cleanUpTestData();

        $createCartHandler = $container->get(CreateNewCartHandler::class);
        $region = new Region(self::REGION);

        foreach ([self::USER_ID_DELIVERY, self::USER_ID_PICKUP] as $userId) {
            $createCartHandler(new CreateNewCartCommand(userId: $userId, region: $region));
        }
    }

    protected function tearDown(): void
    {
        $this->cleanUpTestData();

        parent::tearDown();
    }

    public function testSetsUpCartWithDeliveryType(): void
    {
        $orderDate = new \DateTimeImmutable('+3 hours');

        ($this->handler)(new CartSetUpCommand(
            userId: self::USER_ID_DELIVERY,
            regionCode: self::REGION,
            isDelivery: true,
            orderDate: $orderDate,
            shopId: null,
        ));

        $this->entityManager->clear();

        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_DELIVERY, self::REGION);

        self::assertNotNull($cart, 'Cart must still exist after setup');
        self::assertTrue(
            $cart->getType()->isDelivery(),
            'Cart type must be delivery after dispatching CartSetUpCommand with isDelivery=true',
        );
        self::assertNull($cart->getShopNum(), 'shopNum must be null when shopId=null was passed');
    }

    public function testSetsUpCartWithPickupTypeAndShopId(): void
    {
        $orderDate = new \DateTimeImmutable('+3 hours');

        ($this->handler)(new CartSetUpCommand(
            userId: self::USER_ID_PICKUP,
            regionCode: self::REGION,
            isDelivery: false,
            orderDate: $orderDate,
            shopId: 123,
        ));

        $this->entityManager->clear();

        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_PICKUP, self::REGION);

        self::assertNotNull($cart, 'Cart must still exist after setup');
        self::assertFalse(
            $cart->getType()->isDelivery(),
            'Cart type must not be delivery when isDelivery=false was passed',
        );
        self::assertSame(123, $cart->getShopNum(), 'shopNum must equal the shopId passed to the command');
    }

    public function testDoesNothingWhenCartNotFound(): void
    {
        $this->expectNotToPerformAssertions();

        // Non-existent user — handler must log a warning and return without throwing.
        ($this->handler)(new CartSetUpCommand(
            userId: 99999,
            regionCode: self::REGION,
            isDelivery: true,
            orderDate: new \DateTimeImmutable('+3 hours'),
            shopId: null,
        ));
    }

    private function cleanUpTestData(): void
    {
        $userIds = [self::USER_ID_DELIVERY, self::USER_ID_PICKUP];

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

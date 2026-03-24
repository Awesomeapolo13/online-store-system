<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application\Command\RemoveProductFromCart;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartCommand;
use App\Order\Application\Command\RemoveProductFromCart\RemoveProductFromCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Entity\CartItem;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\Repository\CartRepositoryInterface;
use App\Order\Domain\ValueObject\Cost;
use App\Order\Domain\ValueObject\Price;
use App\Order\Domain\ValueObject\Region;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class RemoveProductFromCartHandlerTest extends KernelTestCase
{
    private const int USER_ID_WITH_ITEM = 501;
    private const int USER_ID_WITHOUT_ITEM = 502;
    private const string SUP_CODE = '369428000000000';
    private const string OTHER_SUP_CODE = '111111111111111';
    private const int REGION = RegionCodeEnum::NIZHNY_NOVGOROD->value;

    private RemoveProductFromCartHandler $handler;
    private CartRepositoryInterface $cartRepository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->handler = $container->get(RemoveProductFromCartHandler::class);
        $this->cartRepository = $container->get(CartRepositoryInterface::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);

        $createCartHandler = $container->get(CreateNewCartHandler::class);
        $region = new Region(self::REGION);

        foreach ([self::USER_ID_WITH_ITEM, self::USER_ID_WITHOUT_ITEM] as $userId) {
            $createCartHandler(new CreateNewCartCommand(userId: $userId, region: $region));
        }

        // Seed an item into USER_ID_WITH_ITEM cart.
        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_WITH_ITEM, self::REGION);
        $qty = 2;
        $price = new Price('75.00');
        $itemCost = Cost::fromString(bcmul('75.00', (string) $qty, 2));
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
        $this->cartRepository->save($cart);
    }

    protected function tearDown(): void
    {
        $userIds = [self::USER_ID_WITH_ITEM, self::USER_ID_WITHOUT_ITEM];

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

        parent::tearDown();
    }

    public function testRemovesItemFromCartAndRecalculatesTotalCost(): void
    {
        $handler = $this->handler;
        $handler(new RemoveProductFromCartCommand(
            userId: self::USER_ID_WITH_ITEM,
            regionCode: self::REGION,
            supCode: self::SUP_CODE,
        ));

        $this->entityManager->clear();

        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_WITH_ITEM, self::REGION);

        self::assertNotNull($cart, 'Cart must still exist after item removal');
        self::assertCount(0, $cart->getCartItems(), 'Cart must have no items after removal');
        self::assertSame('0.00', $cart->getTotalCost()->getCost(), 'Cart totalCost must be 0.00 after removing the only item');
    }

    public function testDoesNothingWhenProductIsNotInCart(): void
    {
        $handler = $this->handler;
        $handler(new RemoveProductFromCartCommand(
            userId: self::USER_ID_WITHOUT_ITEM,
            regionCode: self::REGION,
            supCode: self::OTHER_SUP_CODE,
        ));

        $cart = $this->cartRepository->findActiveByUserIdAndRegion(self::USER_ID_WITHOUT_ITEM, self::REGION);

        self::assertNotNull($cart, 'Cart must still exist');
        self::assertCount(0, $cart->getCartItems(), 'Cart must remain empty when product was not present');
    }
}

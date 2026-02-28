<?php

declare(strict_types=1);

namespace App\Tests\Integration\Order\Application\Command\CreateNewCart;

use App\Order\Application\Command\CreateNewCart\CreateNewCartCommand;
use App\Order\Application\Command\CreateNewCart\CreateNewCartHandler;
use App\Order\Domain\Entity\Cart;
use App\Order\Domain\Enum\RegionCodeEnum;
use App\Order\Domain\ValueObject\Region;
use App\Order\Infrastructure\Repository\DoctrineCartRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class CreateNewCartCommandHandlerTest extends KernelTestCase
{
    private const int USER_ID_CREATE_NEW_CART_1 = 4;
    private const int USER_ID_CREATE_NEW_CART_2 = 5;
    private const int EXPECTED_CART_COUNT = 1;

    private CreateNewCartHandler $handler;
    private DoctrineCartRepository $repository;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();

        $container = self::getContainer();
        $this->handler = $container->get(CreateNewCartHandler::class);
        $this->repository = $container->get(DoctrineCartRepository::class);
        $this->entityManager = $container->get(EntityManagerInterface::class);
    }

    public function testCreateNewCart(): void
    {
        $region = new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value);
        $command = new CreateNewCartCommand(
            userId: self::USER_ID_CREATE_NEW_CART_1,
            region: $region,
        );

        $handler = $this->handler;
        $handler($command);

        $cart = $this->repository->findOneBy(['userId' => self::USER_ID_CREATE_NEW_CART_1, 'region.regionCode' => $region->getRegionCode()]);

        $this::assertInstanceOf(Cart::class, $cart, 'Cart should be instanceof Cart');
        $this::assertSame(self::USER_ID_CREATE_NEW_CART_1, $cart->getUserId(), 'User ID in a cart should be the same');
        $this::assertSame($region->getRegionCode(), $cart->getRegion()->getRegionCode(), 'Region code should be the same');
    }

    public function testCreateNewDoubleCart(): void
    {
        $region = new Region(RegionCodeEnum::NIZHNY_NOVGOROD->value);
        $command = new CreateNewCartCommand(
            userId: self::USER_ID_CREATE_NEW_CART_2,
            region: $region,
        );

        $handler = $this->handler;
        // First try
        $handler($command);
        // Second try
        $handler($command);

        $carts = $this->repository->findBy([
            'userId' => self::USER_ID_CREATE_NEW_CART_2,
            'deletedAt' => null,
        ]);

        self::assertCount(self::EXPECTED_CART_COUNT, $carts, 'Should have only one active cart. Got ' . count($carts));
        foreach ($carts as $cart) {
            $this::assertInstanceOf(Cart::class, $cart, 'Cart should be instanceof Cart');
            $this::assertSame(self::USER_ID_CREATE_NEW_CART_2, $cart->getUserId(), 'User ID in a cart should be the same');
            $this::assertSame($region->getRegionCode(), $cart->getRegion()->getRegionCode(), 'Region code should be the same');
        }
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this
            ->entityManager
            ->createQuery('DELETE FROM ' . Cart::class . ' c WHERE c.userId IN (:userIds)')
            ->setParameter('userIds', [self::USER_ID_CREATE_NEW_CART_1, self::USER_ID_CREATE_NEW_CART_2])
            ->execute();
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Domain\ValueObject;

use App\Order\Domain\Enum\RegionCodeEnum;

readonly class Region
{
    private int $regionCode;

    public function __construct(
        int $regionCode,
    ) {
        $this->assertRegionCode($regionCode);
        $this->regionCode = $regionCode;
    }

    public function getRegionCode(): int
    {
        return $this->regionCode;
    }

    public function isSame(Region $region): bool
    {
        return $this->regionCode === $region->getRegionCode();
    }

    private function assertRegionCode(int $regionCode): void
    {
        if (RegionCodeEnum::tryFrom($regionCode) === null) {
            throw new \InvalidArgumentException('Unsupported region ' . $regionCode);
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Order\Domain\Enum;

enum RegionCodeEnum: int
{
    case NIZHNY_NOVGOROD = 52;
    case MOSCOW = 77;

    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}

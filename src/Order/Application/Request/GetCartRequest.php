<?php

declare(strict_types=1);

namespace App\Order\Application\Request;

use App\Order\Domain\Enum\RegionCodeEnum;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Validator\Constraints as Assert;

final class GetCartRequest
{
    #[Assert\NotBlank]
    #[Assert\Positive]
    public readonly int $userId;

    #[Assert\NotBlank]
    #[Assert\Choice(callback: [RegionCodeEnum::class, 'values'])]
    public readonly int $region;

    public function __construct(Request $request)
    {
        $this->userId = (int) $request->query->get('user_id');
        $this->region = (int) $request->query->get('region');
    }
}

<?php

declare(strict_types=1);

namespace App\DTO\Request;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class UpdateRolePermissionsRequest
{
    /**
     * @param array<int, array{moduleCode: string, canAccess: bool, actions: array<string, bool>}> $permissions
     */
    public function __construct(
        #[Assert\NotBlank]
        public array $permissions,
    ) {
    }
}

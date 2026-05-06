<?php

declare(strict_types=1);

namespace App\Message;

final readonly class ProcessPacaImportMessage
{
    public function __construct(
        public int $importId,
        public int $warehouseId,
        public bool $replaceUnits,
    ) {}
}

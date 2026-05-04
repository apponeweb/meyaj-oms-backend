<?php

declare(strict_types=1);

namespace App\Service;

final class OperationMode
{
    public const INITIALIZING = 'INICIALIZANDO';
    public const PRODUCTION = 'PRODUCCION';

    public function current(): string
    {
        $value = $_ENV['APP_OPERATION_MODE']
            ?? $_SERVER['APP_OPERATION_MODE']
            ?? getenv('APP_OPERATION_MODE')
            ?: self::PRODUCTION;

        return strtoupper(trim((string) $value));
    }

    public function isInitializing(): bool
    {
        return $this->current() === self::INITIALIZING;
    }

    public function isProduction(): bool
    {
        return $this->current() === self::PRODUCTION;
    }
}

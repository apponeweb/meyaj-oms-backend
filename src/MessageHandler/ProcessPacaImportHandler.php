<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Message\ProcessPacaImportMessage;
use App\Service\PacaExcelService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class ProcessPacaImportHandler
{
    public function __construct(
        private PacaExcelService $excelService,
    ) {}

    public function __invoke(ProcessPacaImportMessage $message): void
    {
        $this->excelService->processImport(
            $message->importId,
            $message->warehouseId,
            $message->replaceUnits,
        );
    }
}

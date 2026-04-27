<?php
require __DIR__.'/vendor/autoload.php';
use App\Kernel;
use Symfony\Component\Dotenv\Dotenv;

(new Dotenv())->bootEnv(__DIR__.'/.env');
$kernel = new Kernel($_SERVER['APP_ENV'], (bool) $_SERVER['APP_DEBUG']);
$kernel->boot();
$container = $kernel->getContainer();

try {
    $excelService = $container->get(\App\Service\UserExcelService::class);
    echo "UserExcelService loaded\n";
    $history = $excelService->getHistory();
    echo "History loaded: " . count($history) . " items\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo $e->getTraceAsString() . "\n";
}

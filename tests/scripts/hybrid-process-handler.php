<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Drivers\Hybrid\HybridProcessHandler;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    TestContainer::resolve()->get(HybridProcessHandler::class)->handle();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;

    exit(1);
}

exit(0);

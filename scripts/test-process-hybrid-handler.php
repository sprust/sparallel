<?php

declare(strict_types=1);

use SParallel\Drivers\Hybrid\HybridHandler;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    TestContainer::resolve()->get(HybridHandler::class)->handle();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;

    exit(1);
}

exit(0);

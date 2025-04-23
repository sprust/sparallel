<?php

declare(strict_types=1);

use SParallel\Drivers\Process\ProcessHandler;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

try {
    TestContainer::resolve()->get(ProcessHandler::class)->handle();

    $exitCode = 0;
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;

    $exitCode = 1;
}

exit($exitCode);

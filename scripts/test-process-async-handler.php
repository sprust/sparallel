<?php

declare(strict_types=1);

use SParallel\Drivers\ASync\ASyncHandler;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    TestContainer::resolve()->get(ASyncHandler::class)->handle();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;

    exit(1);
}

exit(0);

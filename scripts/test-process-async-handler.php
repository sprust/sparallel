<?php

declare(strict_types=1);

use SParallel\Drivers\ASync\ASyncProcess;
use SParallel\Tests\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    TestContainer::resolve()->get(ASyncProcess::class)->start();
} catch (Throwable $exception) {
    echo $exception->getMessage() . PHP_EOL;

    exit(1);
}

exit(0);

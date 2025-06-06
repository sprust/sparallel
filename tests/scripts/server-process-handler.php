<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Contracts\ContainerFactoryInterface;
use SParallel\Drivers\Server\ServerWorker;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$exitCode = 0;

try {
    $worker = new ServerWorker();

    $containerFactory = TestContainer::resolve()->get(ContainerFactoryInterface::class);

    $worker->serve(containerFactory: $containerFactory);
} catch (Throwable $exception) {
    fwrite(STDERR, (string) $exception);

    $exitCode = 1;
}

exit($exitCode);

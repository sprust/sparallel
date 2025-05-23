<?php

declare(strict_types=1);

ini_set('memory_limit', '1G');

use SParallel\Drivers\Server\ServerWorker;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../../vendor/autoload.php';

$exitCode = 0;

try {
    TestContainer::resolve()->get(ServerWorker::class)->serve();
} catch (Throwable $exception) {
    fwrite(STDOUT, (string) $exception);

    $exitCode = 1;
}

exit($exitCode);

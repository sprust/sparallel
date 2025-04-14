<?php

declare(strict_types=1);

use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Objects\ResultObject;
use SParallel\Transport\Serializer;
use SParallel\Transport\TaskResultTransport;

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $closure = Serializer::unSerialize(
        $_SERVER[ProcessDriver::VARIABLE_NAME]
    );

    fwrite(STDOUT, TaskResultTransport::serialize(result: $closure()));

    exit(0);
} catch (Throwable $exception) {
    $response = new ResultObject(
        exception: $exception,
    );

    fwrite(STDERR, TaskResultTransport::serialize(exception: $exception));

    exit(1);
}

<?php

declare(strict_types=1);

use SParallel\Contracts\TaskEventsBusInterface;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Objects\Context;
use SParallel\Objects\ResultObject;
use SParallel\Tests\Container;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\Serializer;
use SParallel\Transport\TaskResultTransport;

require_once __DIR__ . '/../vendor/autoload.php';

$closure = Serializer::unSerialize(
    $_SERVER[ProcessDriver::SERIALIZED_CLOSURE_VARIABLE_NAME]
);

$context = ContextTransport::unSerialize(
    $_SERVER[ProcessDriver::SERIALIZED_CONTEXT_VARIABLE_NAME]
);

$container = Container::resolve();

$container->set(Context::class, static fn() => $context);

$taskEventsBus = $container->get(TaskEventsBusInterface::class);

$taskEventsBus->starting($context);

try {
    fwrite(STDOUT, TaskResultTransport::serialize(result: $closure()));

    $exitCode = 0;
} catch (Throwable $exception) {
    $taskEventsBus->failed($context, $exception);

    $response = new ResultObject(
        exception: $exception,
    );

    fwrite(STDERR, TaskResultTransport::serialize(exception: $exception));

    $exitCode = 1;
}

$taskEventsBus->finished($context);

exit($exitCode);

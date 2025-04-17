<?php

declare(strict_types=1);

use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Objects\Context;
use SParallel\Tests\TestContainer;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ResultTransport;

require_once __DIR__ . '/../vendor/autoload.php';

$container = TestContainer::resolve();

$context = $container->get(ContextTransport::class)
    ->unserialize(
        $_SERVER[ProcessDriver::SERIALIZED_CONTEXT_VARIABLE_NAME]
    );

$container->set(Context::class, static fn() => $context);

$driverName          = ProcessDriver::DRIVER_NAME;
$eventsBus           = $container->get(EventsBusInterface::class);
$taskResultTransport = $container->get(ResultTransport::class);

$eventsBus->taskStarting(
    driverName: $driverName,
    context: $context
);

try {
    $closure = $container->get(CallbackTransport::class)
        ->unserialize(
            $_SERVER[ProcessDriver::SERIALIZED_CLOSURE_VARIABLE_NAME]
        );

    fwrite(STDOUT, $taskResultTransport->serialize(result: $closure()));

    $exitCode = 0;
} catch (Throwable $exception) {
    $eventsBus->taskFailed(
        driverName: $driverName,
        context: $context,
        exception: $exception
    );

    fwrite(STDERR, $taskResultTransport->serialize(exception: $exception));

    $exitCode = 1;
}

$eventsBus->taskFinished(
    driverName: $driverName,
    context: $context
);

exit($exitCode);

<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Objects\Context;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\ResultTransport;

class ProcessHandler
{
    public function __construct(
        protected ContainerInterface $container,
        protected CallbackTransport $callbackTransport,
        protected ResultTransport $resultTransport,
        protected ProcessConnectionInterface $connection,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function handle(): void
    {
        $context = $this->container->get(ContextTransport::class)
            ->unserialize(
                $_SERVER[ProcessDriver::SERIALIZED_CONTEXT_VARIABLE_NAME]
            );

        $this->container->set(Context::class, static fn() => $context);

        $driverName = ProcessDriver::DRIVER_NAME;

        $this->eventsBus->taskStarting(
            driverName: $driverName,
            context: $context
        );

        $taskKey = $_SERVER[ProcessDriver::TASK_KEY];

        try {
            $closure = $this->callbackTransport
                ->unserialize(
                    $_SERVER[ProcessDriver::SERIALIZED_CLOSURE_VARIABLE_NAME]
                );

            $this->connection->out(
                data: $this->resultTransport->serialize(
                    key: $taskKey,
                    result: $closure()
                ),
                isError: false
            );
        } catch (\Throwable $exception) {
            $this->eventsBus->taskFailed(
                driverName: $driverName,
                context: $context,
                exception: $exception
            );

            $this->connection->out(
                data: $this->resultTransport->serialize(
                    key: $taskKey,
                    exception: $exception
                ),
                isError: true
            );
        }

        $this->eventsBus->taskFinished(
            driverName: $driverName,
            context: $context
        );
    }
}

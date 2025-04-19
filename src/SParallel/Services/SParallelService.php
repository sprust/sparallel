<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Timer;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\ResultObject;
use Throwable;

class SParallelService
{
    public function __construct(
        protected DriverInterface $driver,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<ResultObject>
     *
     * @throws SParallelTimeoutException
     */
    public function wait(
        array $callbacks,
        int $waitMicroseconds = 0,
        bool $breakAtFirstError = false
    ): Generator {
        $this->eventsBus->flowStarting();

        try {
            return $this->onWait(
                callbacks: $callbacks,
                timeoutSeconds: $waitMicroseconds,
                breakAtFirstError: $breakAtFirstError
            );
        } catch (SParallelTimeoutException $exception) {
            $this->eventsBus->flowFailed($exception);

            throw $exception;
        } catch (Throwable $exception) {
            $this->eventsBus->flowFailed($exception);

            throw new RuntimeException(
                message: $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $this->eventsBus->flowFinished();
        }
    }

    /**
     * @param array<mixed, Closure> $callbacks
     *
     * @return Generator<ResultObject>
     *
     * @throws SParallelTimeoutException
     */
    private function onWait(
        array $callbacks,
        int $timeoutSeconds = 0,
        bool $breakAtFirstError = false
    ): Generator {
        $timer = new Timer(
            timeoutSeconds: $timeoutSeconds
        );

        $generator = $this->driver->run(
            callbacks: $callbacks,
            timer: $timer
        );

        foreach ($generator as $result) {
            if ($breakAtFirstError && $result->error) {
                break;
            }

            yield $result;
        }
    }
}

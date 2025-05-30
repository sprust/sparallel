<?php

declare(strict_types=1);

namespace SParallel;

use Closure;
use Generator;
use RuntimeException;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\SParallelLoggerInterface;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Implementation\Timer;
use SParallel\Objects\TaskResult;
use SParallel\Objects\TaskResults;
use Throwable;

class SParallelWorkers
{
    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected DriverFactoryInterface $driverFactory,
        protected SParallelLoggerInterface $logger,
    ) {
    }

    /**
     * @param array<int|string, Closure> $callbacks
     *
     * @throws ContextCheckerException
     */
    public function waitFirst(
        array &$callbacks,
        int $timeoutSeconds,
        bool $onlySuccess,
        int $workersLimit = 0,
        ?Context $context = null,
    ): ?TaskResult {
        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            workersLimit: $workersLimit,
            getFirstAny: true,
            getFirstSuccess: $onlySuccess,
            context: $context,
        );

        foreach ($generator as $result) {
            return $result;
        }

        return null;
    }

    /**
     * @param array<int|string, Closure> $callbacks
     *
     * @throws ContextCheckerException
     */
    public function wait(
        array &$callbacks,
        int $timeoutSeconds,
        int $workersLimit = 0,
        bool $breakAtFirstError = false,
        ?Context $context = null,
    ): TaskResults {
        $tasksCount = count($callbacks);

        $results = new TaskResults();

        $generator = $this->run(
            callbacks: $callbacks,
            timeoutSeconds: $timeoutSeconds,
            workersLimit: $workersLimit,
            breakAtFirstError: $breakAtFirstError,
            context: $context
        );

        foreach ($generator as $result) {
            /** @var TaskResult $result */

            $results->addResult(
                result: $result
            );
        }

        if ($results->count() === $tasksCount) {
            $results->finish();
        }

        return $results;
    }

    /**
     * @param array<int|string, Closure> $callbacks
     *
     * @return Generator<TaskResult>
     *
     * @throws ContextCheckerException
     */
    public function run(
        array &$callbacks,
        int $timeoutSeconds,
        int $workersLimit = 0,
        bool $breakAtFirstError = false,
        bool $getFirstAny = false,
        bool $getFirstSuccess = false,
        ?Context $context = null
    ): Generator {
        if ($workersLimit < 1) {
            $workersLimit = 100;
        } else {
            $workersLimit = min($workersLimit, 100);
        }

        $this->logger->debug(
            sprintf(
                "workers started [wLim: %d]",
                $workersLimit
            )
        );

        if (is_null($context)) {
            $context = new Context();
        }

        if (!$context->hasChecker(Timer::class)) {
            $context->setChecker(
                new Timer(timeoutSeconds: $timeoutSeconds)
            );
        }

        $this->eventsBus->flowStarting(
            context: $context
        );

        $driver = $this->driverFactory->get();

        try {
            $waitGroup = $driver->run(
                context: $context,
                callbacks: $callbacks,
                timeoutSeconds: $timeoutSeconds
            );

            $brokeResult = null;

            foreach ($waitGroup->get() as $result) {
                $context->check();

                if ($getFirstAny || $getFirstSuccess) {
                    if ($getFirstSuccess && $result->error) {
                        continue;
                    }

                    $waitGroup->cancel();

                    $brokeResult = $result;

                    break;
                }

                if ($breakAtFirstError && $result->error) {
                    $waitGroup->cancel();

                    $brokeResult = $result;

                    break;
                }

                yield $result;
            }

            if ($brokeResult) {
                yield $brokeResult;
            }
        } catch (ContextCheckerException $exception) {
            $this->logger->error(
                sprintf(
                    "workers got context checker exception: %s\n%s",
                    $exception->getMessage(),
                    $exception
                )
            );

            $this->eventsBus->flowFailed(
                context: $context,
                exception: $exception
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->logger->error(
                sprintf(
                    "workers got exception: %s\n%s",
                    $exception->getMessage(),
                    $exception
                )
            );

            $this->eventsBus->flowFailed(
                context: $context,
                exception: $exception
            );

            throw new RuntimeException(
                message: $exception->getMessage(),
                previous: $exception
            );
        } finally {
            $this->logger->debug(
                'workers finished'
            );

            $this->eventsBus->flowFinished(
                context: $context,
            );

            unset($driver);
        }
    }
}

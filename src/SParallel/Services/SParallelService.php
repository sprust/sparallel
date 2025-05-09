<?php

declare(strict_types=1);

namespace SParallel\Services;

use Closure;
use Generator;
use Psr\Log\LoggerInterface;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Entities\Timer;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Flows\FlowFactory;
use SParallel\Objects\TaskResult;
use SParallel\Objects\TaskResults;
use Throwable;

class SParallelService
{
    public function __construct(
        protected EventsBusInterface $eventsBus,
        protected FlowFactory $flowFactory,
        protected LoggerInterface $logger
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
            context: $context
        );

        $result = null;

        foreach ($generator as $result) {
            /** @var TaskResult $result */

            if ($result->error && $onlySuccess) {
                continue;
            }

            break;
        }

        return $result;
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
        ?Context $context = null
    ): Generator {
        if ($workersLimit < 1) {
            $workersLimit = SOMAXCONN;
        } else {
            $workersLimit = min($workersLimit, SOMAXCONN);
        }

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

        try {
            $flow = $this->flowFactory->create(
                callbacks: $callbacks,
                context: $context,
                workersLimit: $workersLimit
            );

            $brokeResult = null;

            foreach ($flow->get() as $result) {
                $context->check();

                if ($breakAtFirstError && $result->error) {
                    $flow->break();

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
                    "service got context checker exception: %s\n%s",
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
                    "service got exception: %s\n%s",
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
            $this->eventsBus->flowFinished(
                context: $context,
            );
        }
    }
}

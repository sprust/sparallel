<?php

declare(strict_types=1);

namespace SParallel;

use Closure;
use Fiber;
use Generator;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\ThreadContinueException;
use SParallel\Implementation\Timer;
use SParallel\Objects\ThreadResult;
use Throwable;

class SParallelThreads
{
    /** @var array<int|string, Fiber> */
    protected array $fibers = [];

    /**
     * @param array<int|string, Closure(Context): mixed> $callbacks
     */
    public function __construct(array &$callbacks)
    {
        foreach ($callbacks as $key => $callback) {
            $this->fibers[$key] = new Fiber($callback);

            unset($callbacks[$key]);
        }
    }

    /**
     * @return Generator<int|string, ThreadResult>
     *
     * @throws ContextCheckerException
     */
    public function run(?int $timeoutSeconds = null, ?Context $context = null): Generator
    {
        if (is_null($context)) {
            $context = new Context();
        }

        if ($timeoutSeconds && !$context->hasChecker(Timer::class)) {
            $context->setChecker(
                new Timer(timeoutSeconds: $timeoutSeconds)
            );
        }

        while (count($this->fibers) > 0) {
            $context->check();

            $keys = array_keys($this->fibers);

            foreach ($keys as $key) {
                $context->check();

                $fiber = $this->fibers[$key];

                try {
                    if (!$fiber->isStarted()) {
                        $fiber->start($context);
                    } elseif ($fiber->isTerminated()) {
                        $result = $fiber->getReturn();

                        unset($this->fibers[$key]);

                        yield new ThreadResult(
                            key: $key,
                            result: $result
                        );
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (Throwable $exception) {
                    unset($this->fibers[$key]);

                    yield new ThreadResult(
                        key: $key,
                        exception: $exception
                    );
                }
            }
        }
    }

    public static function continue(): void
    {
        if (!Fiber::getCurrent()) {
            return;
        }

        try {
            Fiber::suspend();
        } catch (Throwable $exception) {
            throw new ThreadContinueException(
                previous: $exception
            );
        }
    }
}

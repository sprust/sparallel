<?php

declare(strict_types=1);

namespace SParallel;

use Closure;
use Fiber;
use Generator;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\ThreadContinueException;
use SParallel\Implementation\Timer;
use SParallel\Objects\ThreadResult;
use Throwable;

class SParallelThreads
{
    public function __construct(protected CallbackCallerInterface $callbackCaller)
    {
    }

    /**
     * @param array<int|string, Closure(Context): mixed> $callbacks
     *
     * @return Generator<int|string, ThreadResult>
     *
     * @throws ContextCheckerException
     */
    public function run(array &$callbacks, ?int $timeoutSeconds = null, ?Context $context = null): Generator
    {
        if (is_null($context)) {
            $context = new Context();
        }

        if ($timeoutSeconds && !$context->hasChecker(Timer::class)) {
            $context->setChecker(
                new Timer(timeoutSeconds: $timeoutSeconds)
            );
        }

        $fibers = array_map(
            static fn(Closure $callback) => new Fiber($callback),
            $callbacks
        );

        while (count($fibers) > 0) {
            $context->check();

            $keys = array_keys($fibers);

            foreach ($keys as $key) {
                $context->check();

                $fiber = $fibers[$key];

                try {
                    if (!$fiber->isStarted()) {
                        $parameters = $this->callbackCaller->makeParameters(
                            context: $context,
                            callback: $callbacks[$key]
                        );

                        unset($callbacks[$key]);

                        $fiber->start(...$parameters);
                    } elseif ($fiber->isTerminated()) {
                        $result = $fiber->getReturn();

                        unset($fibers[$key]);

                        yield new ThreadResult(
                            key: $key,
                            result: $result
                        );
                    } elseif ($fiber->isSuspended()) {
                        $fiber->resume();
                    }
                } catch (Throwable $exception) {
                    unset($fibers[$key]);

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

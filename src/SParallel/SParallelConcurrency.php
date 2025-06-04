<?php

declare(strict_types=1);

namespace SParallel;

use Closure;
use Fiber;
use Generator;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Exceptions\ConcurrencyContinueException;
use SParallel\Exceptions\ConcurrencyResumeException;
use SParallel\Exceptions\ConcurrencyIsRunningException;
use SParallel\Exceptions\ConcurrencyStartException;
use SParallel\Implementation\Timer;
use SParallel\Objects\ConcurrencyResult;
use Throwable;

// TODO: not completed

class SParallelConcurrency
{
    protected static bool $running = false;

    public function __construct(protected CallbackCallerInterface $callbackCaller)
    {
    }

    /**
     * @param array<int|string, Closure(Context): mixed> $callbacks
     *
     * @return Generator<int|string, ConcurrencyResult>
     *
     * @throws ContextCheckerException
     */
    public function run(
        array &$callbacks,
        int $limitCount = 0,
        ?int $timeoutSeconds = null,
        ?Context $context = null
    ): Generator {
        if (self::$running) {
            throw new ConcurrencyIsRunningException();
        }

        self::$running = true;

        try {
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

            /** @var array<int|string, float> $throttle */
            $throttle = [];

            while (count($fibers) > 0) {
                $context->check();

                if ($limitCount > 0 && count($fibers) >= $limitCount) {
                    $keys = array_keys(array_slice($fibers, 0, 100, true));
                } else {
                    $keys = array_keys($fibers);
                }

                foreach ($keys as $key) {
                    $context->check();

                    $fiber = $fibers[$key];

                    if (!$fiber->isStarted()) {
                        $parameters = $this->callbackCaller->makeParameters(
                            context: $context,
                            callback: $callbacks[$key]
                        );

                        unset($callbacks[$key]);

                        try {
                            $fiber->start(...$parameters);
                        } catch (Throwable $exception) {
                            throw new ConcurrencyStartException(
                                message: $exception->getMessage(),
                                previous: $exception
                            );
                        }

                        $throttle[$key] = microtime(true);;
                    } elseif ($fiber->isTerminated()) {
                        $result = $fiber->getReturn();

                        unset($fibers[$key]);
                        unset($throttle[$key]);

                        yield new ConcurrencyResult(
                            key: $key,
                            result: $result
                        );
                    } elseif ($fiber->isSuspended()) {
                        if ((microtime(true) - $throttle[$key]) < 0.0001) {
                            continue;
                        }

                        $throttle[$key] = microtime(true);

                        try {
                            $fiber->resume();
                        } catch (Throwable $exception) {
                            throw new ConcurrencyResumeException(
                                message: $exception->getMessage(),
                                previous: $exception
                            );
                        }
                    }
                }
            }
        } finally {
            self::$running = false;
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
            throw new ConcurrencyContinueException(
                previous: $exception
            );
        }
    }
}

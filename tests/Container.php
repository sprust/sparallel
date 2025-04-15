<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use Throwable;

class Container implements ContainerInterface
{
    /**
     * @var array<class-string, Closure(): object>
     */
    private array $resolvers;

    /**
     * @var array<class-string, object>
     */
    private static array $cache = [];

    public function __construct()
    {
        $this->resolvers = [
            SyncDriver::class    => static fn() => new SyncDriver(
                beforeTask: static fn() => Counter::increment(),
                afterTask: static fn() => Counter::increment(),
                failedTask: static fn(Throwable $exception) => Counter::increment(),
            ),
            ProcessDriver::class => static fn() => new ProcessDriver(
                __DIR__ . '/process-handler.php param1 param2'
            ),
            ForkDriver::class    => static fn() => new ForkDriver(
                beforeTask: static fn() => Counter::increment(),
                afterTask: static fn() => Counter::increment(),
                failedTask: static fn(Throwable $exception) => Counter::increment(),
            ),
        ];
    }

    /**
     * @template TClass
     *
     * @param class-string<TClass> $id
     *
     * @return TClass
     */
    public function get(string $id)
    {
        if (!$this->has($id)) {
            throw new RuntimeException("No entry found for $id");
        }

        return self::$cache[$id] ??= $this->resolvers[$id]();
    }

    /**
     * @param class-string<object> $id
     */
    public function has(string $id): bool
    {
        return array_key_exists($id, $this->resolvers);
    }
}

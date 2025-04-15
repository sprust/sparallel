<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\TaskEventsBusInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Objects\Context;
use Throwable;

class Container implements ContainerInterface
{
    private static ?Container $container = null;

    /**
     * @template TClass
     *
     * @var array<class-string<TClass>, Closure(): TClass>
     */
    private array $resolvers;

    /**
     * @var array<class-string, object>
     */
    private static array $cache = [];

    public static function resolve(): Container
    {
        return self::$container ??= new Container();
    }

    private function __construct()
    {
        $context = new Context();

        $taskEventBus = new class implements TaskEventsBusInterface {
            public function starting(string $driverName, ?Context $context): void
            {
                Counter::increment();
            }

            public function failed(string $driverName, ?Context $context, Throwable $exception): void
            {
                Counter::increment();
            }

            public function finished(string $driverName, ?Context $context): void
            {
                Counter::increment();
            }
        };

        $this->resolvers = [
            Context::class => static fn() => $context,

            TaskEventsBusInterface::class => static fn() => $taskEventBus,

            SyncDriver::class => static fn() => new SyncDriver(
                context: $context,
                taskEventsBus: $taskEventBus
            ),

            ProcessDriver::class => static fn() => new ProcessDriver(
                scriptPath: __DIR__ . '/process-handler.php param1 param2',
                context: $context,
            ),

            ForkDriver::class => static fn() => new ForkDriver(
                context: $context,
                taskEventsBus: $taskEventBus
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

    /**
     * @template TClass
     *
     * @param class-string<object> $id
     * @param Closure(): TClass    $resolver
     */
    public function set(string $id, Closure $resolver): void
    {
        $this->resolvers[$id] = $resolver;

        unset(self::$cache[$id]);
    }
}

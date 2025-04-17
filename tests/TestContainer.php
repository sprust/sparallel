<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Objects\Context;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\OpisSerializer;
use SParallel\Transport\ResultTransport;

class TestContainer implements ContainerInterface
{
    private static ?TestContainer $container = null;

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

    public static function resolve(): TestContainer
    {
        return self::$container ??= new TestContainer();
    }

    private function __construct()
    {
        $serializer = new OpisSerializer();

        $context           = new Context();
        $eventsBus         = new TestEventsBus();
        $contextTransport  = new ContextTransport(serializer: $serializer);
        $resultTransport   = new ResultTransport(serializer: $serializer);
        $callbackTransport = new CallbackTransport(serializer: $serializer);

        $this->resolvers = [
            SerializerInterface::class => static fn() => $serializer,

            ContextTransport::class => static fn() => $contextTransport,

            ResultTransport::class => static fn() => $resultTransport,

            CallbackTransport::class => static fn() => $callbackTransport,

            Context::class => static fn() => $context,

            EventsBusInterface::class => static fn() => $eventsBus,

            SyncDriver::class => static fn() => new SyncDriver(
                context: $context,
                eventsBus: $eventsBus
            ),

            ProcessDriver::class => static fn() => new ProcessDriver(
                callbackTransport: $callbackTransport,
                resultTransport: $resultTransport,
                contextTransport: $contextTransport,
                scriptPath: __DIR__ . '/process-handler.php' . ' param1 param2',
                context: $context,
            ),

            ForkDriver::class => static fn() => new ForkDriver(
                resultTransport: $resultTransport,
                context: $context,
                eventsBus: $eventsBus
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

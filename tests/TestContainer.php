<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\ASyncScriptPathResolverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Drivers\ASync\ASyncDriver;
use SParallel\Drivers\ASync\ASyncHandler;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Process\ProcessHandler;
use SParallel\Drivers\Process\Service\ProcessConnection;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Objects\Context;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Socket\SocketIO;
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
        $this->resolvers = [
            SerializerInterface::class => fn() => new OpisSerializer(),

            ContextTransport::class => fn() => new ContextTransport(
                serializer: $this->get(SerializerInterface::class)
            ),

            ResultTransport::class => fn() => new ResultTransport(
                serializer: $this->get(SerializerInterface::class)
            ),

            CallbackTransport::class => fn() => new CallbackTransport(
                serializer: $this->get(SerializerInterface::class)
            ),

            Context::class => fn() => new Context(),

            EventsBusInterface::class => fn() => new TestEventsBus(),

            ProcessScriptPathResolverInterface::class => fn() => new ProcessScriptPathResolver(),

            ASyncScriptPathResolverInterface::class => fn() => new ASyncScriptPathResolver(),

            ProcessConnectionInterface::class => fn() => new ProcessConnection(),

            ForkHandler::class => fn() => new ForkHandler(
                resultTransport: $this->get(ResultTransport::class),
                socketIO: $this->get(SocketIO::class),
                context: $this->get(Context::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            SyncDriver::class => fn() => new SyncDriver(
                context: $this->get(Context::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            ProcessDriver::class => fn() => new ProcessDriver(
                connection: $this->get(ProcessConnectionInterface::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                processScriptPathResolver: $this->get(ProcessScriptPathResolverInterface::class),
                context: $this->get(Context::class),
            ),

            ForkDriver::class => fn() => new ForkDriver(
                resultTransport: $this->get(ResultTransport::class),
                forkHandler: $this->get(ForkHandler::class),
                socketIO: $this->get(SocketIO::class),
                context: $this->get(Context::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            ASyncDriver::class => fn() => new ASyncDriver(
                connection: $this->get(ProcessConnectionInterface::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                processScriptPathResolver: $this->get(ASyncScriptPathResolverInterface::class),
                socketIO: $this->get(SocketIO::class),
                context: $this->get(Context::class),
            ),

            ASyncHandler::class => fn() => new ASyncHandler(
                container: $this,
                contextTransport: $this->get(ContextTransport::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                socketIO: $this->get(SocketIO::class),
                forkHandler: $this->get(ForkHandler::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            SocketIO::class => fn() => new SocketIO(),

            ProcessHandler::class => fn() => new ProcessHandler(
                container: $this,
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                connection: $this->get(ProcessConnectionInterface::class),
                eventsBus: $this->get(EventsBusInterface::class),
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

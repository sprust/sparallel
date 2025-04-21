<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\ASyncScriptPathResolverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessScriptPathResolverInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Drivers\ASync\ASyncDriver;
use SParallel\Drivers\ASync\ASyncHandler;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Process\ProcessHandler;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Objects\Context;
use SParallel\Services\Fork\ForkHandler;
use SParallel\Services\Fork\ForkService;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\OpisSerializer;
use SParallel\Transport\ProcessMessagesTransport;
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

            ProcessMessagesTransport::class => fn() => new ProcessMessagesTransport(
                serializer: $this->get(SerializerInterface::class)
            ),

            Context::class => fn() => new Context(),

            EventsBusInterface::class => fn() => new TestEventsBus(),

            ProcessScriptPathResolverInterface::class => fn() => new ProcessScriptPathResolver(),

            ASyncScriptPathResolverInterface::class => fn() => new ASyncScriptPathResolver(),

            ForkHandler::class => fn() => new ForkHandler(
                resultTransport: $this->get(ResultTransport::class),
                socketService: $this->get(SocketService::class),
                context: $this->get(Context::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            SyncDriver::class => fn() => new SyncDriver(
                context: $this->get(Context::class),
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            ProcessDriver::class => fn() => new ProcessDriver(
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                socketService: $this->get(SocketService::class),
                processScriptPathResolver: $this->get(ProcessScriptPathResolverInterface::class),
                eventsBus: $this->get(EventsBusInterface::class),
                messageTransport: $this->get(ProcessMessagesTransport::class),
                context: $this->get(Context::class),
            ),

            ForkDriver::class => fn() => new ForkDriver(
                resultTransport: $this->get(ResultTransport::class),
                forkHandler: $this->get(ForkHandler::class),
                socketService: $this->get(SocketService::class),
                forkService: $this->get(ForkService::class),
            ),

            ForkService::class => fn() => new ForkService(),

            ASyncDriver::class => fn() => new ASyncDriver(
                eventsBus: $this->get(EventsBusInterface::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                processScriptPathResolver: $this->get(ASyncScriptPathResolverInterface::class),
                socketService: $this->get(SocketService::class),
                context: $this->get(Context::class),
            ),

            ASyncHandler::class => fn() => new ASyncHandler(
                container: $this,
                contextTransport: $this->get(ContextTransport::class),
                eventsBus: $this->get(EventsBusInterface::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                socketService: $this->get(SocketService::class),
                forkHandler: $this->get(ForkHandler::class),
                forkService: $this->get(ForkService::class),
            ),

            SocketService::class => fn() => new SocketService(
                eventsBus: $this->get(EventsBusInterface::class),
            ),

            ProcessHandler::class => fn() => new ProcessHandler(
                container: $this,
                socketService: $this->get(SocketService::class),
                messagesTransport: $this->get(ProcessMessagesTransport::class),
                callbackTransport: $this->get(CallbackTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                resultTransport: $this->get(ResultTransport::class),
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

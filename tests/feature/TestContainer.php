<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ForkStarterInterface;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Contracts\TaskManagerFactoryInterface;
use SParallel\Flows\ASync\ASyncFlow;
use SParallel\Flows\ASync\Fork\Forker;
use SParallel\Flows\ASync\Fork\ForkHandler;
use SParallel\Flows\ASync\Fork\ForkService;
use SParallel\Flows\ASync\Fork\ForkTaskManager;
use SParallel\Flows\ASync\Hybrid\HybridProcessHandler;
use SParallel\Flows\ASync\Hybrid\HybridTaskManager;
use SParallel\Flows\ASync\Process\ProcessHandler;
use SParallel\Flows\ASync\Process\ProcessTaskManager;
use SParallel\Flows\FlowFactory;
use SParallel\Flows\TaskManagerFactory;
use SParallel\Services\Callback\CallbackCaller;
use SParallel\Services\Process\ProcessService;
use SParallel\Services\Socket\SocketService;
use SParallel\Services\SParallelService;
use SParallel\Transport\CallbackTransport;
use SParallel\Transport\ContextTransport;
use SParallel\Transport\MessageTransport;
use SParallel\Transport\OpisSerializer;
use SParallel\Transport\ResultTransport;

class TestContainer implements ContainerInterface
{
    private static ?TestContainer $container = null;

    /**
     * @var array<class-string<object>, Closure(): object>
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
            SParallelService::class => fn() => new SParallelService(
                eventsBus: $this->get(EventsBusInterface::class),
                flowFactory: $this->get(FlowFactory::class)
            ),

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

            CallbackCallerInterface::class => fn() => new CallbackCaller(
                container: $this
            ),

            EventsBusInterface::class => fn() => new TestEventsBus(
                processesRepository: $this->get(TestProcessesRepository::class),
                eventsRepository: $this->get(TestEventsRepository::class),
            ),

            ProcessService::class => fn() => new ProcessService(),

            TestProcessesRepository::class   => fn() => new TestProcessesRepository(),
            TestSocketFilesRepository::class => fn() => new TestSocketFilesRepository(),
            TestEventsRepository::class      => fn() => new TestEventsRepository(),

            ForkHandler::class => fn() => new ForkHandler(
                resultTransport: $this->get(ResultTransport::class),
                socketService: $this->get(SocketService::class),
                callbackCaller: $this->get(CallbackCallerInterface::class),
                eventsBus: $this->get(EventsBusInterface::class),
                messageTransport: $this->get(MessageTransport::class),
                processService: $this->get(ProcessService::class),
            ),

            ForkStarterInterface::class => fn() => new TestForkStarter(
                forkHandler: $this->get(ForkHandler::class)
            ),

            TaskManagerFactoryInterface::class => fn() => new TaskManagerFactory(
                container: $this,
            ),

            ProcessTaskManager::class => fn() => new ProcessTaskManager(
                processCommandResolver: $this->get(ProcessCommandResolverInterface::class),
                processService: $this->get(ProcessService::class),
            ),

            ForkTaskManager::class => fn() => new ForkTaskManager(
                forker: $this->get(Forker::class),
                forkService: $this->get(ForkService::class),
            ),

            HybridTaskManager::class => fn() => new HybridTaskManager(
                processCommandResolver: $this->get(HybridProcessCommandResolverInterface::class),
                processService: $this->get(ProcessService::class),
                callbackTransport: $this->get(CallbackTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                socketService: $this->get(SocketService::class),
            ),

            MessageTransport::class => fn() => new MessageTransport(
                serializer: $this->get(SerializerInterface::class)
            ),

            FlowFactory::class => fn() => new FlowFactory(
                socketService: $this->get(SocketService::class),
                taskManagerFactory: $this->get(TaskManagerFactoryInterface::class),
                flow: $this->get(ASyncFlow::class),
            ),

            ASyncFlow::class => fn() => new AsyncFlow(
                socketService: $this->get(SocketService::class),
                contextTransport: $this->get(ContextTransport::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                messageTransport: $this->get(MessageTransport::class),
            ),

            ProcessCommandResolverInterface::class => fn() => new ProcessCommandResolver(),

            HybridProcessCommandResolverInterface::class => fn() => new HybridProcessCommandResolver(),

            Forker::class => fn() => new Forker(
                resultTransport: $this->get(ResultTransport::class),
                socketService: $this->get(SocketService::class),
                callbackCaller: $this->get(CallbackCallerInterface::class),
                eventsBus: $this->get(EventsBusInterface::class),
                forkStarter: $this->get(ForkStarterInterface::class),
            ),

            ForkService::class => fn() => new ForkService(),

            HybridProcessHandler::class => fn() => new HybridProcessHandler(
                contextTransport: $this->get(ContextTransport::class),
                eventsBus: $this->get(EventsBusInterface::class),
                callbackTransport: $this->get(CallbackTransport::class),
                resultTransport: $this->get(ResultTransport::class),
                socketService: $this->get(SocketService::class),
                forkExecutor: $this->get(Forker::class),
                forkService: $this->get(ForkService::class),
                flowFactory: $this->get(FlowFactory::class),
                forkTaskManager: $this->get(ForkTaskManager::class),
                processService: $this->get(ProcessService::class),
            ),

            SocketService::class => fn() => new SocketService(
                socketPathDirectory: __DIR__ . '/../storage/sockets',
            ),

            ProcessHandler::class => fn() => new ProcessHandler(
                socketService: $this->get(SocketService::class),
                messageTransport: $this->get(MessageTransport::class),
                callbackTransport: $this->get(CallbackTransport::class),
                contextTransport: $this->get(ContextTransport::class),
                callbackCaller: $this->get(CallbackCallerInterface::class),
                resultTransport: $this->get(ResultTransport::class),
                eventsBus: $this->get(EventsBusInterface::class),
                processService: $this->get(ProcessService::class),
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

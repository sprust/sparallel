<?php

declare(strict_types=1);

namespace SParallel\Tests;

use Closure;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\FlowInterface;
use SParallel\Contracts\ForkStarterInterface;
use SParallel\Contracts\HybridProcessCommandResolverInterface;
use SParallel\Contracts\ProcessCommandResolverInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Flows\ASync\ASyncFlow;
use SParallel\Flows\ASync\Fork\ForkHandler;
use SParallel\Flows\DriverFactory;
use SParallel\Services\Callback\CallbackCaller;
use SParallel\Services\Socket\SocketService;
use SParallel\Transport\OpisSerializer;

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
            SerializerInterface::class => fn() => new OpisSerializer(),

            CallbackCallerInterface::class => fn() => new CallbackCaller(
                container: $this
            ),

            EventsBusInterface::class => fn() => new TestEventsBus(
                processesRepository: $this->get(TestProcessesRepository::class),
                eventsRepository: $this->get(TestEventsRepository::class),
            ),

            ForkStarterInterface::class => fn() => new TestForkStarter(
                forkHandler: $this->get(ForkHandler::class)
            ),

            DriverFactoryInterface::class => fn() => new DriverFactory(
                container: $this,
            ),

            ProcessCommandResolverInterface::class => fn() => new ProcessCommandResolver(),

            HybridProcessCommandResolverInterface::class => fn() => new HybridProcessCommandResolver(),

            SocketService::class => fn() => new SocketService(
                socketPathDirectory: __DIR__ . '/../storage/sockets',
            ),

            FlowInterface::class => fn() => $this->get(ASyncFlow::class),
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
        if ($this->has($id)) {
            return self::$cache[$id] ??= $this->resolvers[$id]();
        }

        if (interface_exists($id)) {
            throw new RuntimeException("No entry found for $id");
        }

        if (!class_exists($id)) {
            throw new RuntimeException("Class [$id] not found");
        }

        try {
            $reflection = new ReflectionClass($id);

            if (!$reflection->isInstantiable()) {
                throw new RuntimeException("Class [$id] is not instantiable");
            }

            $constructor = $reflection->getConstructor();

            if (is_null($constructor) || $constructor->getNumberOfParameters() === 0) {
                return self::$cache[$id] ??= $reflection->newInstance();
            }

            $params = [];

            foreach ($constructor->getParameters() as $param) {
                $type = $param->getType();

                $isDefaultValueAvailable = $param->isDefaultValueAvailable();

                if ($type && !$isDefaultValueAvailable && !$type->isBuiltin()) {
                    $params[] = $this->get($type->getName());
                } elseif ($isDefaultValueAvailable) {
                    $params[] = $param->getDefaultValue();
                } else {
                    throw new RuntimeException("Cannot resolve parameter \${$param->getName()} for [$id]");
                }
            }

            return self::$cache[$id] ??= $reflection->newInstanceArgs($params);
        } catch (ReflectionException $exception) {
            throw new RuntimeException(
                message: "Failed to instantiate [$id]: " . $exception->getMessage(),
                previous: $exception
            );
        }
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

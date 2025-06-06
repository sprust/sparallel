<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use Closure;
use Dotenv\Dotenv;
use Psr\Container\ContainerInterface;
use ReflectionClass;
use ReflectionException;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\ContainerFactoryInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\RpcClientInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Contracts\SParallelLoggerInterface;
use SParallel\Drivers\DriverFactory;
use SParallel\Implementation\CallbackCaller;
use SParallel\Implementation\OpisSerializer;
use SParallel\Implementation\RpcClient;
use Spiral\Goridge\Relay;
use Spiral\Goridge\RPC\RPC;

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

    public static function flush(): void
    {
        self::$container = null;
    }

    private function __construct()
    {
        $dotenv = Dotenv::createImmutable(__DIR__ . '/../../', '.env');
        $dotenv->load();

        $this->resolvers = [
            ContainerInterface::class => fn() => $this,

            SerializerInterface::class       => fn() => $this->get(OpisSerializer::class),
            CallbackCallerInterface::class   => fn() => $this->get(CallbackCaller::class),
            EventsBusInterface::class        => fn() => $this->get(TestEventsBus::class),
            DriverFactoryInterface::class    => fn() => $this->get(DriverFactory::class),
            SParallelLoggerInterface::class  => fn() => $this->get(TestLogger::class),
            ContainerFactoryInterface::class => fn() => $this->get(TestContainerFactory::class),

            RPC::class => fn() => new RPC(Relay::create("tcp://$_ENV[SERVER_HOST]:$_ENV[SERVER_PORT]")),

            RpcClientInterface::class => fn() => $this->get(RpcClient::class),
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

                /** @phpstan-ignore-next-line method.notFound */
                if ($type && !$isDefaultValueAvailable && !$type->isBuiltin()) {
                    /** @phpstan-ignore-next-line method.notFound */
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

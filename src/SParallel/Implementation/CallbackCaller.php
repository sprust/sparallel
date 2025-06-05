<?php

declare(strict_types=1);

namespace SParallel\Implementation;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Entities\Context;

class CallbackCaller implements CallbackCallerInterface
{
    public function __construct(protected ContainerInterface $container)
    {
    }

    /**
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function call(Context $context, Closure $callback): mixed
    {
        $callbackParameters = $this->makeParameters(
            context: $context,
            callback: $callback
        );

        return $callback(...$callbackParameters);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function makeParameters(Context $context, Closure $callback): array
    {
        $reflection = new ReflectionFunction($callback);

        $reflectionParameters = $reflection->getParameters();

        if (!count($reflectionParameters)) {
            return [];
        }

        $callbackParameters = [];

        foreach ($reflectionParameters as $reflectionParameter) {
            $name = $reflectionParameter->getName();

            /** @phpstan-ignore method.notFound */
            $type = $reflectionParameter->getType()?->getName();

            if (is_null($type)) {
                throw new RuntimeException(
                    message: "Callback parameter [$name] type is not provided.",
                );
            }

            if ($type === Context::class) {
                $callbackParameters[$name] = $context;

                continue;
            }

            $callbackParameters[$name] = $this->container->get($type);
        }

        return $callbackParameters;
    }
}

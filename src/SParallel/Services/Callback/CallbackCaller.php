<?php

declare(strict_types=1);

namespace SParallel\Services\Callback;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use ReflectionFunction;
use RuntimeException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Services\Context;

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
        $reflection = new ReflectionFunction($callback);

        $reflectionParameters = $reflection->getParameters();

        if (!count($reflectionParameters)) {
            return $callback();
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

        return $callback(...$callbackParameters);
    }
}

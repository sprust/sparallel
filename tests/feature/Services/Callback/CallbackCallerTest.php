<?php

declare(strict_types=1);

namespace SParallel\Tests\Services\Callback;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Services\Callback\CallbackCaller;
use SParallel\Services\Canceler;
use SParallel\Tests\TestContainer;

class CallbackCallerTest extends TestCase
{
    protected CallbackCallerInterface $callbackCaller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->callbackCaller = new CallbackCaller(
            TestContainer::resolve()
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testCanceler(): void
    {
        $canceler = new Canceler();

        $callback = static fn(Canceler $canceler) => $canceler;

        $result = $this->callbackCaller->call($callback, $canceler);

        self::assertEquals(
            spl_object_id($canceler),
            spl_object_id($result)
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws ReflectionException
     * @throws NotFoundExceptionInterface
     */
    public function testInjection(): void
    {
        $serializer = TestContainer::resolve()->get(SerializerInterface::class);

        $callback = static fn(SerializerInterface $serializer) => $serializer;

        $result = $this->callbackCaller->call($callback, new Canceler());

        self::assertEquals(
            spl_object_id($serializer),
            spl_object_id($result)
        );
    }
}

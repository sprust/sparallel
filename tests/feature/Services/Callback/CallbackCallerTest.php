<?php

declare(strict_types=1);

namespace SParallel\TestsFeature\Services\Callback;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;
use SParallel\Contracts\CallbackCallerInterface;
use SParallel\Contracts\SerializerInterface;
use SParallel\Entities\Context;
use SParallel\Implementation\CallbackCaller;
use SParallel\TestsImplementation\TestContainer;

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
    public function testDependencyInjection(): void
    {
        $context = new Context();

        $callback = static function (SerializerInterface $serializer, Context $context) {
            return [$serializer, $context];
        };

        [$gotSerializer, $gotContext] = $this->callbackCaller->call(
            callback: $callback,
            context: $context
        );

        $serializer = TestContainer::resolve()->get(SerializerInterface::class);

        self::assertEquals(
            spl_object_id($serializer),
            spl_object_id($gotSerializer)
        );

        self::assertEquals(
            spl_object_id($context),
            spl_object_id($gotContext)
        );
    }
}

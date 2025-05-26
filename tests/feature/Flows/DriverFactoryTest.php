<?php

declare(strict_types=1);

namespace SParallel\TestsFeature\Flows;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Drivers\DriverFactory;
use SParallel\Drivers\Server\ServerDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\TestsImplementation\TestContainer;

class DriverFactoryTest extends TestCase
{
    private ContainerInterface $container;
    private DriverFactoryInterface $factory;

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->container = TestContainer::resolve();
        $this->factory   = $this->container->get(DriverFactoryInterface::class);
    }

    public function testDefault(): void
    {
        self::assertEquals(
            SyncDriver::class,
            $this->factory->get()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testForce(): void
    {
        $this->factory->forceDriver(
            $this->container->get(ServerDriver::class)
        );

        self::assertEquals(
            ServerDriver::class,
            $this->factory->get()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testConstructor(): void
    {
        $factory = new DriverFactory(
            $this->container,
            $this->container->get(ServerDriver::class)
        );

        self::assertEquals(
            ServerDriver::class,
            $factory->get()::class,
        );
    }
}

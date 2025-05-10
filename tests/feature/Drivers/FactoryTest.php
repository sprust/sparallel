<?php

declare(strict_types=1);

namespace SParallel\Tests\Drivers;

use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Flows\ASync\Fork\ForkDriver;
use SParallel\Flows\ASync\Hybrid\HybridDriver;
use SParallel\Flows\ASync\Process\ProcessDriver;
use SParallel\Flows\DriverFactory;
use SParallel\Tests\TestContainer;

class FactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testDefault(): void
    {
        $factory = new DriverFactory(
            container: TestContainer::resolve()
        );

        self::assertEquals(
            ForkDriver::class,
            $factory->detect()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testInConsole(): void
    {
        $factory = new DriverFactory(
            container: TestContainer::resolve(),
            isRunningInConsole: true
        );

        self::assertEquals(
            ForkDriver::class,
            $factory->detect()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testNotInConsole(): void
    {
        $factory = new DriverFactory(
            container: TestContainer::resolve(),
            isRunningInConsole: false
        );

        self::assertEquals(
            ProcessDriver::class,
            $factory->detect()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testManual(): void
    {
        $factory = new DriverFactory(
            container: TestContainer::resolve(),
            isRunningInConsole: false,
            driver: TestContainer::resolve()->get(HybridDriver::class),
        );

        self::assertEquals(
            HybridDriver::class,
            $factory->detect()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testHybrid(): void
    {
        $factory = new DriverFactory(
            container: TestContainer::resolve(),
            isRunningInConsole: false,
            useHybridDriverInsteadProcess: true
        );

        self::assertEquals(
            HybridDriver::class,
            $factory->detect()::class,
        );
    }
}

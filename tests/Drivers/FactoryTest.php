<?php

declare(strict_types=1);

namespace SParallel\Tests\Drivers;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Drivers\Factory;
use PHPUnit\Framework\TestCase;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Hybrid\HybridDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Tests\TestContainer;

class FactoryTest extends TestCase
{
    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testDefault(): void
    {
        $factory = new Factory(
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
        $factory = new Factory(
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
        $factory = new Factory(
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
        $factory = new Factory(
            container: TestContainer::resolve(),
            isRunningInConsole: false,
            driver: TestContainer::resolve()->get(SyncDriver::class)
        );

        self::assertEquals(
            SyncDriver::class,
            $factory->detect()::class,
        );
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function testHybrid(): void
    {
        $factory = new Factory(
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

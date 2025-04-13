<?php

declare(strict_types=1);

namespace SParallel\Tests\Drivers;

use SParallel\Drivers\Factory;
use PHPUnit\Framework\TestCase;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;

class FactoryTest extends TestCase
{
    public function testDefault(): void
    {
        $factory = new Factory();

        self::assertEquals(
            ForkDriver::class,
            $factory->detect()::class,
        );
    }

    public function testInConsole(): void
    {
        $factory = new Factory(
            isRunningInConsole: true
        );

        self::assertEquals(
            ForkDriver::class,
            $factory->detect()::class,
        );
    }

    public function testNotInConsole(): void
    {
        $factory = new Factory(
            isRunningInConsole: false
        );

        self::assertEquals(
            ProcessDriver::class,
            $factory->detect()::class,
        );
    }

    public function testManual(): void
    {
        $factory = new Factory(
            isRunningInConsole: false,
            driver: new SyncDriver()
        );

        self::assertEquals(
            SyncDriver::class,
            $factory->detect()::class,
        );
    }
}

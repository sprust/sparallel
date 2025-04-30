<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Hybrid\HybridDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use SParallel\Services\SParallelService;
use SParallel\TestCases\SParallelServiceTestCasesTrait;
use SParallel\Tests\TestContainer;

class SParallelServiceTest extends TestCase
{
    use SParallelServiceTestCasesTrait;

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function success(DriverInterface $driver): void
    {
        $this->onSuccess(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function waitFirstOnlySuccess(DriverInterface $driver): void
    {
        $this->onWaitFirstOnlySuccess(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function waitFirstNotOnlySuccess(DriverInterface $driver): void
    {
        $this->onWaitFirstNotOnlySuccess(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function workersLimit(DriverInterface $driver): void
    {
        $this->onWorkersLimit(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function failure(DriverInterface $driver): void
    {
        $this->onFailure(
            service: $this->makeServiceByDriver($driver),
        );
    }

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $this->onTimeout(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function breakAtFirstError(DriverInterface $driver): void
    {
        $this->onBreakAtFirstError(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function bigPayload(DriverInterface $driver): void
    {
        $this->onBigPayload(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversMemoryLeakDataProvider')]
    public function memoryLeak(DriverInterface $driver): void
    {
        $this->onMemoryLeak(
            service: $this->makeServiceByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function events(DriverInterface $driver): void
    {
        $this->onEvents(
            service: $this->makeServiceByDriver($driver),
            context: new Context()
        );
    }

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function driversDataProvider(): array
    {
        $container = TestContainer::resolve();

        return [
            'sync'    => self::makeDriverCase(
                driver: $container->get(id: SyncDriver::class)
            ),
            'process' => self::makeDriverCase(
                driver: $container->get(id: ProcessDriver::class)
            ),
            'fork'    => self::makeDriverCase(
                driver: $container->get(id: ForkDriver::class)
            ),
            'hybrid'  => self::makeDriverCase(
                driver: $container->get(id: HybridDriver::class)
            ),
        ];
    }

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function driversMemoryLeakDataProvider(): array
    {
        $container = TestContainer::resolve();

        return [
            'process' => self::makeDriverCase(
                driver: $container->get(id: ProcessDriver::class)
            ),
            'fork'    => self::makeDriverCase(
                driver: $container->get(id: ForkDriver::class)
            ),
            'hybrid'  => self::makeDriverCase(
                driver: $container->get(id: HybridDriver::class)
            ),
        ];
    }

    /**
     * @return array{driver: DriverInterface}
     */
    private static function makeDriverCase(DriverInterface $driver): array
    {
        return [
            'driver' => $driver,
        ];
    }

    private function makeServiceByDriver(DriverInterface $driver): SParallelService
    {
        return new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );
    }
}

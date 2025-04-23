<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Services\SParallelService;
use SParallel\Tests\TestContainer;

class SParallelServiceTest extends TestCase
{
    use SParallelServiceTestCasesTrait;

    /**
     * @throws SParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function success(DriverInterface $driver): void
    {
        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );

        $this->onSuccess($service);
    }

    /**
     * @throws SParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function failure(DriverInterface $driver): void
    {
        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );

        $this->onFailure($service);
    }

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );

        $this->onTimeout($service);
    }

    /**
     * @throws SParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function breakAtFirstError(DriverInterface $driver): void
    {
        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );

        $this->onBreakAtFirstError($service);
    }

    /**
     * @throws SParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversMemoryLeakDataProvider')]
    public function memoryLeak(DriverInterface $driver): void
    {
        // TODO: hide an exception to terminal for fork driver
        // TODO: off warnings

        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class),
        );

        $this->onMemoryLeak($service);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws SParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function events(DriverInterface $driver): void
    {
        $service = new SParallelService(
            driver: $driver,
            eventsBus: TestContainer::resolve()->get(EventsBusInterface::class)
        );

        $this->onEvents($service, static fn() => TestContainer::resolve());
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
}

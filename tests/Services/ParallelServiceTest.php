<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Exceptions\ParallelTimeoutException;
use SParallel\ParallelServiceTestCasesTrait;
use SParallel\Services\ParallelService;
use SParallel\Tests\Container;

class ParallelServiceTest extends TestCase
{
    use ParallelServiceTestCasesTrait;

    /**
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function success(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $this->onSuccess($service);
    }

    /**
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function failure(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $this->onFailure($service);
    }

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $this->onTimeout($service);
    }

    /**
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function breakAtFirstError(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $this->onBreakAtFirstError($service);
    }

    /**
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversMemoryLeakDataProvider')]
    public function memoryLeak(DriverInterface $driver): void
    {
        // TODO: hide an exception to terminal for fork driver
        // TODO: off warnings

        $service = new ParallelService(
            driver: $driver
        );

        $this->onMemoryLeak($service);
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function events(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver,
        );

        $this->onEvents($service, static fn() => Container::resolve());
    }

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function driversDataProvider(): array
    {
        $container = Container::resolve();

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
        $container = Container::resolve();

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

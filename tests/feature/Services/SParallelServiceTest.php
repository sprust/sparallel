<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
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
use SParallel\Tests\TestEventsRepository;
use SParallel\Tests\TestProcessesRepository;
use SParallel\Tests\TestSocketFilesRepository;

class SParallelServiceTest extends TestCase
{
    use SParallelServiceTestCasesTrait;

    protected TestProcessesRepository $processesRepository;
    protected TestSocketFilesRepository $socketFilesRepository;
    protected TestEventsRepository $eventsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        $this->processesRepository   = TestContainer::resolve()->get(TestProcessesRepository::class);
        $this->socketFilesRepository = TestContainer::resolve()->get(TestSocketFilesRepository::class);
        $this->eventsRepository      = TestContainer::resolve()->get(TestEventsRepository::class);

        $this->processesRepository->flush();
        $this->socketFilesRepository->flush();
        $this->eventsRepository->flush();
    }

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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        // TODO
        //$this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
    }

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $this->onTimeout(
            service: $this->makeServiceByDriver($driver),
        );

        // TODO
        //$this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        // TODO
        //$this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
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

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function eventsSuccess(DriverInterface $driver): void
    {
        $customEventName = 'customEvent';

        $context = new Context();

        $context->addValue(
            $customEventName,
            static fn() => TestContainer::resolve()->get(TestEventsRepository::class)->add($customEventName)
        );

        $callbacks = [
            'first'  => static fn(Context $context) => $context
                ->getValue($customEventName),
            'second' => static fn(Context $context) => $context
                ->getValue($customEventName),
        ];

        $callbacksCount = count($callbacks);

        $service = $this->makeServiceByDriver($driver);

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);

        $this->assertEventsCount('flowStarting', 1);
        $this->assertEventsCount('flowFailed', 0);
        $this->assertEventsCount('flowFinished', 1);
        $this->assertEventsCount('taskStarting', 2);
        $this->assertEventsCount('taskFailed', 0);
        $this->assertEventsCount('taskFinished', 2);
        $this->assertEventsCount($customEventName, 2);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function eventsFailed(DriverInterface $driver): void
    {
        $customEventName = 'customEvent';

        $context = new Context();

        $context->addValue(
            $customEventName,
            static fn() => TestContainer::resolve()->get(TestEventsRepository::class)->add($customEventName)
        );

        $callbacks = [
            'first'  => static function (Context $context) use ($customEventName) {
                $context->getValue($customEventName);

                throw new RuntimeException();
            },
            'second' => static function (Context $context) use ($customEventName) {
                $context->getValue($customEventName);

                throw new RuntimeException();
            },
        ];

        $callbacksCount = count($callbacks);

        $service = $this->makeServiceByDriver($driver);

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        $this->assertActiveProcessesCount(0);
        $this->assertActiveSocketServersCount(0);

        $this->assertEventsCount('flowStarting', 1);
        $this->assertEventsCount('flowFailed', 0);
        $this->assertEventsCount('flowFinished', 1);
        $this->assertEventsCount('taskStarting', 2);
        $this->assertEventsCount('taskFailed', 2);
        $this->assertEventsCount('taskFinished', 2);
        $this->assertEventsCount($customEventName, 2);
    }

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function driversDataProvider(): array
    {
        $container = TestContainer::resolve();

        return [
            'sync' => self::makeDriverCase(
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

    private function assertActiveProcessesCount(int $expectedCount): void
    {
        $activeProcessesCount = $this->processesRepository->getActiveCount();

        self::assertEquals(
            $expectedCount,
            $activeProcessesCount,
            "Expected active processes count: $expectedCount, got: $activeProcessesCount"
        );
    }

    private function assertActiveSocketServersCount(int $expectedCount): void
    {
        $openedSocketsCount = $this->socketFilesRepository->getCount();

        self::assertEquals(
            $expectedCount,
            $openedSocketsCount,
            "Expected active sockets count: $expectedCount, got: $openedSocketsCount"
        );
    }

    private function assertEventsCount(string $eventName, int $expectedCount): void
    {
        $openedSocketsCount = $this->eventsRepository->getEventsCount($eventName);

        self::assertEquals(
            $expectedCount,
            $openedSocketsCount,
            "Expected [$eventName] events count: $expectedCount, got: $openedSocketsCount"
        );
    }
}

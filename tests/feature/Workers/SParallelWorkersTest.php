<?php

declare(strict_types=1);

namespace SParallel\TestsFeature\Workers;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\DriverFactory;
use SParallel\Drivers\Server\ServerDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Entities\Context;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\SParallelWorkers;
use SParallel\TestCases\SParallelWorkersTestCasesTrait;
use SParallel\TestsImplementation\TestContainer;
use SParallel\TestsImplementation\TestEventsRepository;
use SParallel\TestsImplementation\TestLogger;

class SParallelWorkersTest extends TestCase
{
    use SParallelWorkersTestCasesTrait;

    protected TestEventsRepository $eventsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        TestLogger::flush();

        $this->eventsRepository = TestContainer::resolve()->get(TestEventsRepository::class);

        $this->eventsRepository->flush();
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function success(DriverInterface $driver): void
    {
        $this->onSuccess(
            workers: $this->makeWorkersByDriver($driver)
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function waitFirstOnlySuccess(DriverInterface $driver): void
    {
        $this->onWaitFirstOnlySuccess(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function waitFirstNotOnlySuccess(DriverInterface $driver): void
    {
        $this->onWaitFirstNotOnlySuccess(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function workersLimit(DriverInterface $driver): void
    {
        $this->onWorkersLimit(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function failure(DriverInterface $driver): void
    {
        $this->onFailure(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $this->onTimeout(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
    public function breakAtFirstError(DriverInterface $driver): void
    {
        $this->onBreakAtFirstError(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('asyncDriversDataProvider')]
    public function bigPayload(DriverInterface $driver): void
    {
        $this->onBigPayload(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('asyncDriversDataProvider')]
    public function memoryLeak(DriverInterface $driver): void
    {
        $this->onMemoryLeak(
            workers: $this->makeWorkersByDriver($driver),
        );
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    #[DataProvider('allDriversDataProvider')]
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

        $workers = $this->makeWorkersByDriver($driver);

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

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
    #[DataProvider('allDriversDataProvider')]
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

        $workers = $this->makeWorkersByDriver($driver);

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

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
    public static function allDriversDataProvider(): array
    {
        $container = TestContainer::resolve();

        return [
            'sync'   => self::makeDriverCase(
                driver: $container->get(id: SyncDriver::class)
            ),
            'server' => self::makeDriverCase(
                driver: $container->get(id: ServerDriver::class)
            ),
        ];
    }

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function asyncDriversDataProvider(): array
    {
        $container = TestContainer::resolve();

        return [
            'server' => self::makeDriverCase(
                driver: $container->get(id: ServerDriver::class)
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

    private function makeWorkersByDriver(DriverInterface $driver): SParallelWorkers
    {
        $container = TestContainer::resolve();

        $container->get(DriverFactory::class)
            ->forceDriver($driver);

        return $container->get(SParallelWorkers::class);
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

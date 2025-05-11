<?php

declare(strict_types=1);

namespace SParallel\TestsFeature\Services;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use SParallel\Services\SParallelService;
use SParallel\TestCases\SParallelServiceTestCasesTrait;
use SParallel\TestsImplementation\TestContainer;
use SParallel\TestsImplementation\TestEventsRepository;
use SParallel\TestsImplementation\TestFlowTypeResolver;
use SParallel\TestsImplementation\TestLogger;
use SParallel\TestsImplementation\TestSocketFilesRepository;

class SParallelServiceSyncTest extends TestCase
{
    use SParallelServiceTestCasesTrait;

    protected TestSocketFilesRepository $socketFilesRepository;
    protected TestEventsRepository $eventsRepository;

    protected function setUp(): void
    {
        parent::setUp();

        TestLogger::flush();
        TestFlowTypeResolver::$isAsync = true;
        TestLogger::flush();

        $this->socketFilesRepository = TestContainer::resolve()->get(TestSocketFilesRepository::class);
        $this->eventsRepository      = TestContainer::resolve()->get(TestEventsRepository::class);

        $this->socketFilesRepository->flush();
        $this->eventsRepository->flush();
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function success(): void
    {
        $this->onSuccess(
            service: $this->makeServiceByDriver()
        );

        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function waitFirstOnlySuccess(): void
    {
        $this->onWaitFirstOnlySuccess(
            service: $this->makeServiceByDriver(),
        );

        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function waitFirstNotOnlySuccess(): void
    {
        $this->onWaitFirstNotOnlySuccess(
            service: $this->makeServiceByDriver(),
        );

        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function failure(): void
    {
        $this->onFailure(
            service: $this->makeServiceByDriver(),
        );

        $this->assertActiveSocketServersCount(0);
    }

    #[Test]
    public function timeout(): void
    {
        $this->onTimeout(
            service: $this->makeServiceByDriver(),
        );

        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function breakAtFirstError(): void
    {
        $this->onBreakAtFirstError(
            service: $this->makeServiceByDriver(),
        );

        $this->assertActiveSocketServersCount(0);
    }

    /**
     * @throws ContextCheckerException
     */
    #[Test]
    public function eventsSuccess(): void
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

        $service = $this->makeServiceByDriver();

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

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
    public function eventsFailed(): void
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

        $service = $this->makeServiceByDriver();

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            context: $context
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        $this->assertActiveSocketServersCount(0);

        $this->assertEventsCount('flowStarting', 1);
        $this->assertEventsCount('flowFailed', 0);
        $this->assertEventsCount('flowFinished', 1);
        $this->assertEventsCount('taskStarting', 2);
        $this->assertEventsCount('taskFailed', 2);
        $this->assertEventsCount('taskFinished', 2);
        $this->assertEventsCount($customEventName, 2);
    }

    private function makeServiceByDriver(): SParallelService
    {
        $container = TestContainer::resolve();

        return $container->get(SParallelService::class);
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

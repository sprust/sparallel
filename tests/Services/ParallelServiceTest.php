<?php

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Exceptions\ParallelTimeoutException;
use SParallel\Services\ParallelService;
use SParallel\Tests\ContainerTrait;
use SParallel\Tests\Counter;

class ParallelServiceTest extends TestCase
{
    use ContainerTrait;

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

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => 'second',
            ]
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());

        self::assertEquals(2, $results->count());

        $resultsArray = $results->getResults();

        self::assertArrayHasKey(
            'first',
            $resultsArray
        );

        self::assertArrayHasKey(
            'second',
            $resultsArray
        );

        self::assertEquals('first', $resultsArray['first']->result);
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

        $exceptionMessage = uniqid();

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => throw new RuntimeException($exceptionMessage),
            ]
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());

        $resultsArray = $results->getResults();

        self::assertCount(2, $resultsArray);

        self::assertArrayHasKey(
            'first',
            $resultsArray
        );

        self::assertArrayHasKey(
            'second',
            $resultsArray
        );

        self::assertEquals('first', $resultsArray['first']->result);
        self::assertTrue(is_null($resultsArray['first']->error));

        $resultErrorObject = $resultsArray['second']->error;

        self::assertFalse(is_null($resultErrorObject));
        self::assertEquals(RuntimeException::class, $resultErrorObject->exceptionClass);
        self::assertEquals($exceptionMessage, $resultErrorObject->message);

        $failedResultsArray = $results->getFailed();

        self::assertCount(1, $failedResultsArray);

        self::assertArrayNotHasKey(
            'first',
            $failedResultsArray
        );

        self::assertArrayHasKey(
            'second',
            $failedResultsArray
        );

        $resultErrorObject = $failedResultsArray['second']->error;

        self::assertFalse(is_null($resultErrorObject));
        self::assertEquals(RuntimeException::class, $resultErrorObject->exceptionClass);
        self::assertEquals($exceptionMessage, $resultErrorObject->message);
    }

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function timeout(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $exception = null;

        try {
            $service->wait(
                callbacks: [
                    'first'  => static fn() => 'first',
                    'second' => static fn() => usleep(200),
                ],
                waitMicroseconds: 1
            );
        } catch (ParallelTimeoutException $exception) {
            //
        } finally {
            self::assertInstanceOf(
                ParallelTimeoutException::class,
                $exception
            );
        }
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

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => usleep(200) || throw new RuntimeException(),
                'third'  => static fn() => 'third',
            ],
            breakAtFirstError: true
        );

        self::assertFalse($results->isFinished());
        self::assertTrue($results->hasFailed());

        self::assertTrue(
            iterator_count($results->getResults()) >= 0
        );
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

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static function () {
                    ini_set('memory_limit', '60m');

                    str_repeat(uniqid(), 1000000000);
                },
            ],
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());

        self::assertTrue(
            iterator_count($results->getResults()) === 2
        );

        self::assertTrue(
            iterator_count($results->getFailed()) === 1
        );
    }

    /**
     * @throws ParallelTimeoutException
     */
    #[Test]
    #[DataProvider('driversActionCallbacksDataProvider')]
    public function actionCallbacks(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver,
        );

        Counter::reset();

        $results = $service->wait(
            callbacks: [
                'first' => static fn() => 'first',
            ],
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());

        self::assertTrue(
            iterator_count($results->getResults()) === 1
        );

        self::assertEquals(
            2,
            Counter::getCount()
        );

        Counter::reset();

        $results = $service->wait(
            callbacks: [
                'first' => static fn() => throw new RuntimeException(),
            ],
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());

        self::assertTrue(
            iterator_count($results->getResults()) === 1
        );

        self::assertEquals(
            3,
            Counter::getCount()
        );
    }

    /**
     * @return array{driver: DriverInterface}[]
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * */
    public static function driversDataProvider(): array
    {
        $container = self::getContainer();

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
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function driversMemoryLeakDataProvider(): array
    {
        $container = self::getContainer();

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
     * @return array{driver: DriverInterface}[]
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public static function driversActionCallbacksDataProvider(): array
    {
        $container = self::getContainer();

        return [
            'sync' => self::makeDriverCase(
                driver: $container->get(id: SyncDriver::class)
            ),
            'fork' => self::makeDriverCase(
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

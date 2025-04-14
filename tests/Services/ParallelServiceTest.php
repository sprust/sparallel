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
use SParallel\Objects\ResultObject;
use SParallel\Services\ParallelService;
use SParallel\Tests\ContainerTrait;

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

        self::assertEquals(2, $results->count());

        /**
         * @var array<string, ResultObject> $resultsArray
         */
        $resultsArray = iterator_to_array($results->getResults());

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

        /**
         * @var array<string, ResultObject> $resultsArray
         */
        $resultsArray = iterator_to_array($results->getResults());

        self::assertCount(2, $resultsArray);

        self::assertArrayHasKey(
            'first',
            $resultsArray
        );

        self::assertArrayHasKey(
            'second',
            $resultsArray
        );

        self::assertTrue(
            $results->hasFailed()
        );

        self::assertEquals('first', $resultsArray['first']->result);
        self::assertTrue(is_null($resultsArray['first']->error));

        $resultErrorObject = $resultsArray['second']->error;

        self::assertFalse(is_null($resultErrorObject));
        self::assertEquals(RuntimeException::class, $resultErrorObject->exceptionClass);
        self::assertEquals($exceptionMessage, $resultErrorObject->message);

        /**
         * @var array<string, ResultObject> $failedResultsArray
         */
        $failedResultsArray = iterator_to_array($results->getFailed());

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
        // TODO: remove notice to terminal for fork driver

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
     * @return array{driver: DriverInterface}
     */
    private static function makeDriverCase(DriverInterface $driver): array
    {
        return [
            'driver' => $driver,
        ];
    }
}

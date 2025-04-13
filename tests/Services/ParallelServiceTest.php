<?php

namespace SParallel\Tests\Services;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\Sync\SyncDriver;
use SParallel\Objects\ResultObject;
use SParallel\Services\ParallelService;

class ParallelServiceTest extends TestCase
{
    #[Test]
    #[DataProvider('driversDataProvider')]
    public function success(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $results = $service->run([
            'first'  => static fn() => 'first',
            'second' => static fn() => 'second',
        ]);

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

    #[Test]
    #[DataProvider('driversDataProvider')]
    public function failure(DriverInterface $driver): void
    {
        $service = new ParallelService(
            driver: $driver
        );

        $exceptionMessage = uniqid();

        $results = $service->run([
            'first'  => static fn() => 'first',
            'second' => static fn() => throw new RuntimeException($exceptionMessage),
        ]);

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

    /**
     * @return array{driver: DriverInterface}[]
     */
    public static function driversDataProvider(): array
    {
        return [
            'sync' => self::makeDriverCase(
                driver: new SyncDriver()
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

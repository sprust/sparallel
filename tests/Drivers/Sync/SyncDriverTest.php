<?php

namespace SParallel\Tests\Drivers\Sync;

use RuntimeException;
use SParallel\Drivers\Sync\SyncDriver;
use PHPUnit\Framework\TestCase;
use SParallel\Objects\ResultObject;

class SyncDriverTest extends TestCase
{
    public function testSuccess(): void
    {
        $driver = new SyncDriver();

        $results = $driver->run([
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

    public function testFailure(): void
    {
        $driver = new SyncDriver();

        $exceptionMessage = uniqid();

        $results = $driver->run([
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
}

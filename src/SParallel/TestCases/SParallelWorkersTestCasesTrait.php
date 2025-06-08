<?php

declare(strict_types=1);

namespace SParallel\TestCases;

use RuntimeException;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\SParallelWorkers;

/** @phpstan-ignore-next-line trait.unused */
trait SParallelWorkersTestCasesTrait
{
    /**
     * @throws ContextCheckerException
     */
    protected function onSuccess(SParallelWorkers $workers): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => 'second',
        ];

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1
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
     * @throws ContextCheckerException
     */
    protected function onWaitFirstOnlySuccess(SParallelWorkers $workers): void
    {
        $callbacks = [
            'first'  => static function () {
                sleep(1);

                return 'first';
            },
            'second' => static fn() => throw new RuntimeException('second'),
        ];

        $result = $workers->waitFirst(
            callbacks: $callbacks,
            timeoutSeconds: 2,
            onlySuccess: true,
        );

        self::assertTrue(
            is_null($result->error)
        );

        self::assertEquals(
            'first',
            $result->result
        );
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onWaitFirstNotOnlySuccess(SParallelWorkers $workers): void
    {
        $callbacks = [
            'second' => static fn() => throw new RuntimeException('second'),
            'first'  => static function () {
                sleep(1);

                return 'first';
            },
        ];

        $result = $workers->waitFirst(
            callbacks: $callbacks,
            timeoutSeconds: 2,
            onlySuccess: false,
        );

        self::assertFalse(
            is_null($result->error)
        );

        self::assertEquals(
            'second',
            $result->error->message
        );
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onWorkersLimit(SParallelWorkers $workers): void
    {
        $callbacks = [
            'first'  => static function () {
                sleep(1);

                return 'first';
            },
            'second' => static function () {
                sleep(1);

                return 'second';
            },
        ];

        $startTime = time();

        $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 4,
            workersLimit: 1
        );

        self::assertTrue(
            (time() - $startTime) >= 2
        );
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onFailure(SParallelWorkers $workers): void
    {
        $exceptionMessage = uniqid();

        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => throw new RuntimeException($exceptionMessage),
        ];

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1
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

    protected function onTimeout(SParallelWorkers $workers): void
    {
        $exception = null;

        $callbacks = [
            'second' => static fn() => sleep(2),
            'first'  => static fn() => 'first',
        ];

        try {
            $workers->wait(
                callbacks: $callbacks,
                timeoutSeconds: 1
            );
        } catch (ContextCheckerException $exception) {
            //
        } finally {
            self::assertInstanceOf(
                ContextCheckerException::class,
                $exception
            );
        }
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onBreakAtFirstError(SParallelWorkers $workers): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => throw new RuntimeException(),
            'third'  => static fn() => sleep(2),
        ];

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            breakAtFirstError: true
        );

        self::assertFalse($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() >= 0);
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onBigPayload(SParallelWorkers $workers): void
    {
        $parameters = str_repeat(uniqid(more_entropy: true), 1);

        $callbacks = [
            'first'  => static fn() => $parameters,
            'second' => static fn() => $parameters,
            'third'  => static fn() => $parameters,
        ];

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 2,
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertEquals(3, $results->count());
    }

    /**
     * @throws ContextCheckerException
     */
    protected function onMemoryLeak(SParallelWorkers $workers): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static function () {
                ini_set('memory_limit', '60m');

                return str_repeat(uniqid(), 1000000000);
            },
        ];

        $results = $workers->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === 2);
        self::assertTrue(count($results->getFailed()) === 1);
    }
}

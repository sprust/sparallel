<?php

declare(strict_types=1);

namespace SParallel\TestCases;

use RuntimeException;
use SParallel\Contracts\ContextResolverInterface;
use SParallel\Exceptions\CancelerException;
use SParallel\Services\SParallelService;
use SParallel\Tests\TestCounter;

/** @phpstan-ignore-next-line trait.unused */
trait SParallelServiceTestCasesTrait
{
    /**
     * @throws CancelerException
     */
    protected function onSuccess(SParallelService $service): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => 'second',
        ];

        $results = $service->wait(
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
     * @throws CancelerException
     */
    protected function onFailure(SParallelService $service): void
    {
        $exceptionMessage = uniqid();

        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => throw new RuntimeException($exceptionMessage),
        ];

        $results = $service->wait(
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

    protected function onTimeout(SParallelService $service): void
    {
        $exception = null;

        $callbacks = [
            'second' => static fn() => sleep(2),
            'first'  => static fn() => 'first',
        ];

        try {
            $service->wait(
                callbacks: $callbacks,
                timeoutSeconds: 1
            );
        } catch (CancelerException $exception) {
            //
        } finally {
            self::assertInstanceOf(
                CancelerException::class,
                $exception
            );
        }
    }

    /**
     * @throws CancelerException
     */
    protected function onBreakAtFirstError(SParallelService $service): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static fn() => throw new RuntimeException(),
            'third'  => static fn() => sleep(2),
        ];

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
            breakAtFirstError: true
        );

        self::assertFalse($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() >= 0);
    }

    /**
     * @throws CancelerException
     */
    protected function onBigPayload(SParallelService $service): void
    {
        $parameters = str_repeat(uniqid(more_entropy: true), 1);

        $callbacks = [
            'first'  => static fn() => $parameters,
            'second' => static fn() => $parameters,
            'third'  => static fn() => $parameters,
        ];

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 2,
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertEquals(3, $results->count());
    }

    /**
     * @throws CancelerException
     */
    protected function onMemoryLeak(SParallelService $service): void
    {
        $callbacks = [
            'first'  => static fn() => 'first',
            'second' => static function () {
                ini_set('memory_limit', '60m');

                return str_repeat(uniqid(), 1000000000);
            },
        ];

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === 2);
        self::assertTrue(count($results->getFailed()) === 1);
    }

    /**
     * @throws CancelerException
     */
    protected function onEvents(
        SParallelService $service,
        ContextResolverInterface $contextResolver
    ): void {
        $counterKey = uniqid();

        $context = $contextResolver->get();

        $context->add(
            $counterKey,
            static fn() => TestCounter::increment()
        );

        TestCounter::reset();

        $callbacks = [
            'first'  => static fn() => $contextResolver->get()
                ->get($counterKey),
            'second' => static fn() => $contextResolver->get()
                ->get($counterKey),
        ];

        $callbacksCount = count($callbacks);

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        self::assertEquals(
            (3 * $callbacksCount) + 2,
            TestCounter::getCount()
        );

        TestCounter::reset();

        $callbacks = [
            'first'  => static function () use ($contextResolver, $counterKey) {
                $contextResolver->get()
                    ->get($counterKey);

                throw new RuntimeException();
            },
            'second' => static function () use ($contextResolver, $counterKey) {
                $contextResolver->get()
                    ->get($counterKey);

                throw new RuntimeException();
            },
        ];

        $callbacksCount = count($callbacks);

        $results = $service->wait(
            callbacks: $callbacks,
            timeoutSeconds: 1,
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        self::assertEquals(
            (4 * $callbacksCount) + 2,
            TestCounter::getCount()
        );
    }
}

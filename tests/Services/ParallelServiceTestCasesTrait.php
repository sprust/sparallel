<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use SParallel\Exceptions\ParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Services\ParallelService;
use SParallel\Tests\Container;
use SParallel\Tests\Counter;

trait ParallelServiceTestCasesTrait
{
    /**
     * @throws ParallelTimeoutException
     */
    protected function onSuccess(ParallelService $service): void
    {
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
    protected function onFailure(ParallelService $service): void
    {
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

    protected function onTimeout(ParallelService $service): void
    {
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
    protected function onBreakAtFirstError(ParallelService $service): void
    {
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
        self::assertTrue($results->count() >= 0);
    }

    /**
     * @throws ParallelTimeoutException
     */
    protected function onMemoryLeak(ParallelService $service): void
    {
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
        self::assertTrue($results->count() === 2);
        self::assertTrue(count($results->getFailed()) === 1);
    }

    /**
     * @param Closure(): ContainerInterface $containerResolver
     *
     * @throws ParallelTimeoutException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     */
    protected function onEvents(ParallelService $service, Closure $containerResolver): void
    {
        $counterKey = uniqid();

        $container = $containerResolver();

        $container->get(Context::class)
            ->add(
                $counterKey,
                static fn() => Counter::increment()
            );

        Counter::reset();

        $callbacks = [
            'first'  => static fn() => $containerResolver()
                ->get(Context::class)
                ->get($counterKey),
            'second' => static fn() => $containerResolver()
                ->get(Context::class)
                ->get($counterKey),
        ];

        $callbacksCount = count($callbacks);

        $results = $service->wait(
            callbacks: $callbacks,
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        self::assertEquals(
            3 * $callbacksCount,
            Counter::getCount()
        );

        Counter::reset();

        $callbacks = [
            'first'  => static function () use ($containerResolver, $counterKey) {
                $containerResolver()
                    ->get(Context::class)
                    ->get($counterKey);

                throw new RuntimeException();
            },
            'second' => static function () use ($containerResolver, $counterKey) {
                $containerResolver()
                    ->get(Context::class)
                    ->get($counterKey);

                throw new RuntimeException();
            },
        ];

        $callbacksCount = count($callbacks);

        $results = $service->wait(
            callbacks: $callbacks,
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === $callbacksCount);

        self::assertEquals(
            4 * $callbacksCount,
            Counter::getCount()
        );
    }
}

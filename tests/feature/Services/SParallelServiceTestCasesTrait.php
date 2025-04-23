<?php

declare(strict_types=1);

namespace SParallel\Tests\Services;

use Closure;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use RuntimeException;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Exceptions\SParallelTimeoutException;
use SParallel\Objects\Context;
use SParallel\Services\SParallelService;
use SParallel\Tests\TestCounter;

trait SParallelServiceTestCasesTrait
{
    /**
     * @throws SParallelTimeoutException
     */
    protected function onSuccess(SParallelService $service): void
    {
        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => 'second',
            ],
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
     * @throws SParallelTimeoutException
     */
    protected function onFailure(SParallelService $service): void
    {
        $exceptionMessage = uniqid();

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => throw new RuntimeException($exceptionMessage),
            ],
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

        try {
            $service->wait(
                callbacks: [
                    'second' => static fn() => sleep(2),
                    'first'  => static fn() => 'first',
                ],
                timeoutSeconds: 1
            );
        } catch (SParallelTimeoutException $exception) {
            //
        } finally {
            self::assertInstanceOf(
                SParallelTimeoutException::class,
                $exception
            );
        }
    }

    /**
     * @throws SParallelTimeoutException
     */
    protected function onBreakAtFirstError(SParallelService $service): void
    {
        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static fn() => throw new RuntimeException(),
                'third'  => static fn() => sleep(2),
            ],
            timeoutSeconds: 1,
            breakAtFirstError: true
        );

        self::assertFalse($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() >= 0);
    }

    /**
     * @throws SParallelTimeoutException
     */
    protected function onBigPayload(SParallelService $service): void
    {
        $parameters = str_repeat(uniqid(more_entropy: true), 500000);

        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => $parameters,
                'second' => static fn() => $parameters,
                'third'  => static fn() => $parameters,
            ],
            timeoutSeconds: 2,
        );

        self::assertTrue($results->isFinished());
        self::assertFalse($results->hasFailed());
        self::assertEquals(3, $results->count());
    }

    /**
     * @throws SParallelTimeoutException
     */
    protected function onMemoryLeak(SParallelService $service): void
    {
        $results = $service->wait(
            callbacks: [
                'first'  => static fn() => 'first',
                'second' => static function () {
                    ini_set('memory_limit', '60m');

                    str_repeat(uniqid(), 1000000000);
                },
            ],
            timeoutSeconds: 1,
        );

        self::assertTrue($results->isFinished());
        self::assertTrue($results->hasFailed());
        self::assertTrue($results->count() === 2);
        self::assertTrue(count($results->getFailed()) === 1);
    }

    /**
     * @param Closure(): ContainerInterface $containerResolver
     *
     * @throws SParallelTimeoutException
     * @throws NotFoundExceptionInterface
     * @throws ContainerExceptionInterface
     * @see EventsBusInterface
     *
     */
    protected function onEvents(SParallelService $service, Closure $containerResolver): void
    {
        $counterKey = uniqid();

        $container = $containerResolver();

        /** @var Context $context */
        $context = $container->get(Context::class);

        $context->add(
            $counterKey,
            static fn() => TestCounter::increment()
        );

        TestCounter::reset();

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

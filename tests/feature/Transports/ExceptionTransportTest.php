<?php

declare(strict_types=1);

namespace SParallel\TestsFeature\Transports;

use PHPUnit\Framework\TestCase;
use SParallel\TestsImplementation\TestContainer;
use SParallel\Transport\ExceptionTransport;
use Exception;
use Throwable;

class ExceptionTransportTest extends TestCase
{
    private ExceptionTransport $exceptionTransport;

    protected function setUp(): void
    {
        parent::setUp();

        $this->exceptionTransport = TestContainer::resolve()->get(ExceptionTransport::class);
    }

    public function test(): void
    {
        $exception = new Exception(
            message: 'Test exception',
            code: 777,
            previous: new Exception(
                message: 'Test previous exception',
                code: 888,
                previous: new Exception(
                    message: 'Test previous of previous exception',
                    code: 999,
                )
            )
        );

        $unserialized = $this->exceptionTransport->unserialize(
            $this->exceptionTransport->serialize($exception)
        );

        $this->assertEqualsException(
            expected: $exception,
            actual: $unserialized,
        );

        $this->assertEqualsException(
            expected: $exception->getPrevious(),
            actual: $unserialized->getPrevious(),
        );

        $this->assertEqualsException(
            expected: $exception->getPrevious()->getPrevious(),
            actual: $unserialized->getPrevious()->getPrevious(),
        );
    }

    private function assertEqualsException(Throwable $expected, ?Throwable $actual): void
    {
        $this->assertNotNull($actual);

        self::assertEquals(
            $expected->getMessage(),
            $actual->getMessage(),
        );

        self::assertEquals(
            $expected->getCode(),
            $actual->getCode(),
        );

        self::assertEquals(
            $expected->getTraceAsString(),
            $actual->getTraceAsString(),
        );
    }
}

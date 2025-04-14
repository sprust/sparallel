<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use Closure;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ResultsObject;

class ForkWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, Closure> $callbacks
     */
    public function __construct(
        protected array $callbacks,
    ) {
    }

    public function current(): ResultsObject
    {
        // TODO
        return new ResultsObject();
    }

    public function break(): void
    {
        // TODO
    }
}

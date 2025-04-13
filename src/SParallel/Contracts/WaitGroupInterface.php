<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Objects\ResultsObject;

interface WaitGroupInterface
{
    public function current(): ResultsObject;

    public function break(): void;
}

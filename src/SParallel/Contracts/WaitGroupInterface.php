<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use SParallel\Objects\ResultObject;

interface WaitGroupInterface
{
    public function current(): ?ResultObject;

    public function break(): void;
}

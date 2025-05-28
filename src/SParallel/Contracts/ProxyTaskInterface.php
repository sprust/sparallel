<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface ProxyTaskInterface
{
    public function start(): void;

    public function isFinished(): bool;
}

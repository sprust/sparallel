<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Closure;

interface TaskInterface
{
    public function getKey(): int|string;

    public function getPid(): int;

    public function isFinished(): bool;

    public function finish(): void;

    public function getOutput(): ?string;

    public function getCallback(): Closure;
}

<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Symfony\Component\Process\Process;

class ProcessTask
{
    public function __construct(
        public ?int $pid,
        public mixed $taskKey,
        public string $serializedCallback,
        public Process $process,
    ) {
    }
}

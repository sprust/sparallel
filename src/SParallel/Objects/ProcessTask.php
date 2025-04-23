<?php

declare(strict_types=1);

namespace SParallel\Objects;

use Symfony\Component\Process\Process;

class ProcessTask
{
    public function __construct(
        public mixed $taskKey,
        public string $serializedCallback,
        public Process $process,
    ) {
    }
}

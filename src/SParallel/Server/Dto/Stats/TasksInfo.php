<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class TasksInfo
{
    public function __construct(
        public int $waitingCount,
        public int $finishedCount,
    ) {
    }
}

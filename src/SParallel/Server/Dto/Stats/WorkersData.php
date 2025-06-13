<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class WorkersData
{
    public function __construct(
        public WorkersInfo $workers,
        public TasksInfo $tasks,
    ) {
    }
}

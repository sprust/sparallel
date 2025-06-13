<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class TasksInfo
{
    public function __construct(
        public int $waitingCount,
        public int $finishedCount,
        public int $addedTotalCount,
        public int $reAddedTotalCount,
        public int $tookTotalCount,
        public int $finishedTotalCount,
        public int $successTotalCount,
        public int $errorTotalCount,
        public int $timeoutTotalCount,
    ) {
    }
}

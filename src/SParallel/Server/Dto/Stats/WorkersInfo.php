<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class WorkersInfo
{
    public function __construct(
        public int $count,
        public int $freeCount,
        public int $busyCount,
        public int $loadPercent,
    ) {
    }
}

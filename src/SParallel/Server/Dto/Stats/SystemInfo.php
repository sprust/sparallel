<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class SystemInfo
{
    public function __construct(
        public int $numGoroutine,
        public int $allocMiB,
        public int $totalAllocMiB,
        public int $sysMiB,
        public int $numGC,
    ) {
    }
}

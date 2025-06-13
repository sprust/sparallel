<?php

declare(strict_types=1);

namespace SParallel\Server\Dto\Stats;

readonly class ServerStats
{
    public function __construct(
        public \DateTimeImmutable $dateTime,
        public SystemInfo $system,
        public WorkersData $workers,
    ) {
    }
}

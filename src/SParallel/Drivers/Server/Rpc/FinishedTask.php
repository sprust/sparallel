<?php

declare(strict_types=1);

namespace SParallel\Drivers\Server\Rpc;

readonly class FinishedTask
{
    public function __construct(
        public string $groupUuid,
        public string $taskUuid,
        public bool $isFinished,
        public string $response,
        public bool $isError,
    ) {
    }
}

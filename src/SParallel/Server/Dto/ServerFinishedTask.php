<?php

declare(strict_types=1);

namespace SParallel\Server\Dto;

readonly class ServerFinishedTask
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

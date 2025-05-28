<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\Aggregate;

use MongoDB\BSON\Document;

readonly class AggregateResultReply
{
    public function __construct(
        public bool $isFinished,
        public string $error,
        public ?Document $result,
        public string $nextUuid,
    ) {
    }
}

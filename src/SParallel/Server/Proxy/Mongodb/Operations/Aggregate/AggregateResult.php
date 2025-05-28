<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\Aggregate;

use MongoDB\BSON\ObjectId;

readonly class AggregateResult
{
    public function __construct(
        public ObjectId|string|int|float|null $insertedId,
    ) {
    }
}

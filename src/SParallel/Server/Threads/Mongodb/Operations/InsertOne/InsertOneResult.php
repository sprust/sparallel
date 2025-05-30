<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb\Operations\InsertOne;

use MongoDB\BSON\ObjectId;

readonly class InsertOneResult
{
    public function __construct(
        public ObjectId|string|int|float|null $insertedId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\UpdateOne;

use MongoDB\BSON\ObjectId;

readonly class UpdateOneResult
{
    public function __construct(
        public int $matchedCount,
        public int $modifiedCount,
        public int $upsertedCount,
        public ObjectId|string|int|float|null $upsertedId,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\BulkWrite;

use MongoDB\BSON\ObjectId;

readonly class BulkWriteResult
{
    /**
     * @param array<ObjectId|string|int|float|null> $upsertedIds
     */
    public function __construct(
        public int $insertedCount,
        public int $matchedCount,
        public int $modifiedCount,
        public int $deletedCount,
        public int $upsertedCount,
        public array $upsertedIds,
    ) {
    }
}

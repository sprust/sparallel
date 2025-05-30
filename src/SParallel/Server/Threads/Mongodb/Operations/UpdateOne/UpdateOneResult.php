<?php

declare(strict_types=1);

namespace SParallel\Server\Threads\Mongodb\Operations\UpdateOne;

use MongoDB\BSON\ObjectId;
use MongoDB\UpdateResult;

class UpdateOneResult extends UpdateResult
{
    public function __construct(
        public int $matchedCount,
        public int $modifiedCount,
        public int $upsertedCount,
        public ObjectId|string|int|float|null $upsertedId,
    ) {
    }

    public function getMatchedCount(): int
    {
        return $this->matchedCount;
    }

    public function getModifiedCount(): int
    {
        return $this->modifiedCount;
    }

    public function getUpsertedCount(): int
    {
        return $this->upsertedCount;
    }

    public function getUpsertedId(): ObjectId|string|int|float|null
    {
        return $this->upsertedId;
    }

    public function isAcknowledged(): true
    {
        return true;
    }
}

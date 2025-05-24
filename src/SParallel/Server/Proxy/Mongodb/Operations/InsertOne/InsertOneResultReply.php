<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\InsertOne;

readonly class InsertOneResultReply
{
    public function __construct(
        public bool $isFinished,
        public string $error,
        public ?InsertOneResult $result,
    ) {
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Server\Proxy\Mongodb\Operations\UpdateOne;

readonly class UpdateOneResultReply
{
    public function __construct(
        public bool $isFinished,
        public string $error,
        public ?UpdateOneResult $result,
    ) {
    }
}

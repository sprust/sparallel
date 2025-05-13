<?php

declare(strict_types=1);

namespace SParallel\Objects;

use SParallel\Enum\MessageOperationTypeEnum;

readonly class Message
{
    public function __construct(
        public MessageOperationTypeEnum $operation,
        public int|string $taskKey,
        public string $serializedContext = '',
        public string $payload = '',
    ) {
    }
}

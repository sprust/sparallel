<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use SParallel\Objects\TaskResult;
use Throwable;

class TaskResultTransport
{
    public function __construct(
        protected SerializerInterface $serializer,
    ) {
    }

    public function serialize(int|string $taskKey, ?Throwable $exception = null, mixed $result = null): string
    {
        return $this->serializer->serialize(
            new TaskResult(
                taskKey: $taskKey,
                exception: $exception,
                result: $result,
            )
        );
    }

    public function unserialize(string $data): TaskResult
    {
        $response = $this->serializer->unserialize($data);

        if ($response instanceof TaskResult) {
            return $response;
        }

        throw new UnserializeException(
            expected: TaskResult::class,
            got: gettype($response)
        );
    }
}

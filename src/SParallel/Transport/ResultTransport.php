<?php

declare(strict_types=1);

namespace SParallel\Transport;

use RuntimeException;
use SParallel\Contracts\SerializerInterface;
use SParallel\Objects\TaskResult;
use Throwable;

class ResultTransport
{
    public function __construct(
        protected SerializerInterface $serializer,
    ) {
    }

    public function serialize(mixed $key, ?Throwable $exception = null, mixed $result = null): string
    {
        return $this->serializer->serialize(
            new TaskResult(
                key: $key,
                exception: $exception,
                result: $result,
            )
        );
    }

    public function unserialize(?string $data): TaskResult
    {
        try {
            $response = $this->serializer->unserialize($data);
        } catch (Throwable) {
            throw new RuntimeException(
                message: "Failed to unserialize task response:\n$data",
            );
        }

        if ($response instanceof TaskResult) {
            return $response;
        }

        throw new RuntimeException(
            message: "Unexpected task response:\n$data",
        );
    }
}

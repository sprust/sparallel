<?php

declare(strict_types=1);

namespace SParallel\Transport;

use RuntimeException;
use SParallel\Contracts\SerializerInterface;
use SParallel\Objects\ResultObject;
use Throwable;

class ResultTransport
{
    public function __construct(
        protected SerializerInterface $serializer,
    ) {
    }

    public function serialize(?Throwable $exception = null, mixed $result = null): string
    {
        return $this->serializer->serialize(
            new ResultObject(
                exception: $exception,
                result: $result,
            )
        );
    }

    public function unserialize(?string $data): ResultObject
    {
        try {
            $response = $this->serializer->unserialize($data);
        } catch (Throwable) {
            return new ResultObject(
                exception: new RuntimeException(
                    message: "Failed to unserialize task response:\n$data",
                )
            );
        }

        if ($response instanceof ResultObject) {
            return $response;
        }

        return new ResultObject(
            exception: new RuntimeException(
                message: "Unexpected task response:\n$data",
            )
        );
    }
}

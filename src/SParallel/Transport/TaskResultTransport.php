<?php

declare(strict_types=1);

namespace SParallel\Transport;

use RuntimeException;
use SParallel\Objects\ResultObject;
use Throwable;

class TaskResultTransport
{
    public static function serialize(?Throwable $exception = null, mixed $result = null): string
    {
        return Serializer::serialize(
            new ResultObject(
                exception: $exception,
                result: $result,
            )
        );
    }

    public static function unSerialize(?string $data): ResultObject
    {
        try {
            $response = Serializer::unSerialize($data);
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
                message: 'Unexpected task response',
            )
        );
    }
}

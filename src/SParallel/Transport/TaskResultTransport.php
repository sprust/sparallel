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
        protected ExceptionTransport $exceptionTransport,
    ) {
    }

    public function serialize(int|string $taskKey, ?Throwable $exception = null, mixed $result = null): string
    {
        return json_encode([
            'taskKey'   => $taskKey,
            'exception' => $exception ? $this->exceptionTransport->serialize($exception) : null,
            'result'    => $this->serializer->serialize($result),
        ]);
    }

    public function unserialize(string $data): TaskResult
    {
        $response = json_decode($data, true);

        $isArray = is_array($response);

        if ($isArray
            && array_key_exists('taskKey', $response)
            && array_key_exists('exception', $response)
            && array_key_exists('result', $response)
        ) {
            return new TaskResult(
                taskKey: $response['taskKey'],
                exception: $response['exception']
                    ? $this->exceptionTransport->unserialize($response['exception'])
                    : null,
                result: $this->serializer->unserialize($response['result']),
            );
        }

        throw new UnserializeException(
            expected: 'array with keys "taskKey", "exception", "result"',
            got: $isArray ? implode(', ', array_keys($response)) : gettype($response)
        );
    }
}

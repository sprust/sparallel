<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use SParallel\Server\Workers\ServerTask;

readonly class ServerTaskTransport
{
    public function __construct(
        protected SerializerInterface $serializer,
        protected ContextTransport $contextTransport,
        protected CallbackTransport $callbackTransport,
    ) {
    }

    public function serialize(ServerTask $task): string
    {
        return $this->serializer->serialize([
            'ctx' => $this->contextTransport->serialize($task->context),
            'tk'  => serialize($task->key),
            'cb'  => $this->callbackTransport->serialize($task->callback),
        ]);
    }

    public function unserialize(string $data): ServerTask
    {
        $response = $this->serializer->unserialize($data);

        if (!is_array($response)) {
            throw new UnserializeException(
                expected: 'array',
                got: gettype($response)
            );
        }

        if (!isset($response['ctx'], $response['tk'], $response['cb'])) {
            throw new UnserializeException(
                expected: 'array with keys "ctx", "tk", "cb"',
                got: implode(', ', array_keys($response))
            );
        }

        return new ServerTask(
            context: $this->contextTransport->unserialize($response['ctx']),
            key: unserialize($response['tk']),
            callback: $this->callbackTransport->unserialize($response['cb']),
        );
    }
}

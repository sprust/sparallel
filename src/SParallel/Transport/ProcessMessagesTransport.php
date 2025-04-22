<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use SParallel\Objects\ProcessChildMessage;
use SParallel\Objects\ProcessParentMessage;

class ProcessMessagesTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serializeParent(ProcessParentMessage $message): string
    {
        return $this->serializer->serialize($message);
    }

    public function unserializeParent(string $data): ProcessParentMessage
    {
        $message = $this->serializer->unserialize($data);

        if ($message instanceof ProcessParentMessage) {
            return $message;
        }

        throw new UnserializeException(
            expected: ProcessParentMessage::class,
            got: $message
        );
    }

    public function serializeChild(ProcessChildMessage $message): string
    {
        return $this->serializer->serialize($message);
    }

    public function unserializeChild(string $data): ProcessChildMessage
    {
        $message = $this->serializer->unserialize($data);

        if ($message instanceof ProcessChildMessage) {
            return $message;
        }

        throw new UnserializeException(
            expected: ProcessChildMessage::class,
            got: $message
        );
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use SParallel\Objects\Message;

class MessageTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serialize(Message $message): string
    {
        return $this->serializer->serialize($message);
    }

    public function unserialize(string $data): Message
    {
        $message = $this->serializer->unserialize($data);

        if ($message instanceof Message) {
            return $message;
        }

        throw new UnserializeException(
            expected: Message::class,
            got: gettype($message)
        );
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Transport;

use RuntimeException;
use SParallel\Contracts\SerializerInterface;
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
        $context = $this->serializer->unserialize($data);

        if ($context instanceof ProcessParentMessage) {
            return $context;
        }

        $class = ProcessParentMessage::class;

        throw new RuntimeException(
            "Failed to unserialize $class, got: " . gettype($context) . '.'
        );
    }

    public function serializeChild(ProcessChildMessage $message): string
    {
        return $this->serializer->serialize($message);
    }

    public function unserializeChild(string $data): ProcessChildMessage
    {
        $context = $this->serializer->unserialize($data);

        if ($context instanceof ProcessChildMessage) {
            return $context;
        }

        $class = ProcessChildMessage::class;

        throw new RuntimeException(
            "Failed to unserialize $class, got: " . gettype($context) . '.'
        );
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Transport;

use Closure;
use RuntimeException;
use SParallel\Contracts\SerializerInterface;

class CallbackTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serialize(Closure $callback): string
    {
        return $this->serializer->serialize($callback);
    }

    public function unserialize(?string $data): ?Closure
    {
        if (is_null($data)) {
            return null;
        }

        $serialized = $this->serializer->unserialize($data);

        if (!is_callable($serialized)) {
            throw new RuntimeException(
                message: "Failed to unserialize task callback:\n$data",
            );
        }

        return $serialized;
    }
}

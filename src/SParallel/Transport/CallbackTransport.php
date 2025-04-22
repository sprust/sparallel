<?php

declare(strict_types=1);

namespace SParallel\Transport;

use Closure;
use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;

class CallbackTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serialize(Closure $callback): string
    {
        return $this->serializer->serialize($callback);
    }

    public function unserialize(string $data): ?Closure
    {
        $serialized = $this->serializer->unserialize($data);

        if (!is_callable($serialized)) {
            throw new UnserializeException(
                expected: 'callback',
                got: $serialized
            );
        }

        return $serialized;
    }
}

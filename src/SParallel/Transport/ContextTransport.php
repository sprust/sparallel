<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Entities\Context;
use SParallel\Exceptions\UnserializeException;

class ContextTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serialize(Context $context): string
    {
        return $this->serializer->serialize($context);
    }

    public function unserialize(string $data): Context
    {
        $context = $this->serializer->unserialize($data);

        if ($context instanceof Context) {
            return $context;
        }

        throw new UnserializeException(
            expected: Context::class,
            got: gettype($context)
        );
    }
}

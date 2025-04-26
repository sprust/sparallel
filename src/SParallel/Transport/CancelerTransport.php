<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;
use SParallel\Exceptions\UnserializeException;
use SParallel\Services\Canceler;

class CancelerTransport
{
    public function __construct(protected SerializerInterface $serializer)
    {
    }

    public function serialize(Canceler $canceler): string
    {
        return $this->serializer->serialize($canceler);
    }

    public function unserialize(string $data): Canceler
    {
        $canceler = $this->serializer->unserialize($data);

        if ($canceler instanceof Canceler) {
            return $canceler;
        }

        throw new UnserializeException(
            expected: Canceler::class,
            got: $canceler
        );
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Transport;

use SParallel\Contracts\SerializerInterface;

class OpisSerializer implements SerializerInterface
{
    public function serialize(mixed $data): string
    {
        return \Opis\Closure\serialize($data);
    }

    public function unserialize(?string $data): mixed
    {
        if (is_null($data)) {
            return null;
        }

        return \Opis\Closure\unserialize($data);
    }
}

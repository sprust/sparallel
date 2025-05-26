<?php

declare(strict_types=1);

namespace SParallel\Implementation;

use SParallel\Contracts\SerializerInterface;

class OpisSerializer implements SerializerInterface
{
    public function serialize(mixed $data): string
    {
        return \Opis\Closure\serialize($data);
    }

    public function unserialize(string $data): mixed
    {
        return \Opis\Closure\unserialize($data);
    }
}

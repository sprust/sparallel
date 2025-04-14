<?php

declare(strict_types=1);

namespace SParallel\Transport;

class Serializer
{
    public static function serialize(mixed $data): string
    {
        return \Opis\Closure\serialize($data);
    }

    public static function unSerialize(string $data): mixed
    {
        return \Opis\Closure\unserialize($data);
    }
}

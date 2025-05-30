<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface MongodbConnectionUriFactoryInterface
{
    public function get(string $name = 'default'): string;
}

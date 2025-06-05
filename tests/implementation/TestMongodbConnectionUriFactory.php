<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use SParallel\Contracts\MongodbConnectionUriFactoryInterface;

class TestMongodbConnectionUriFactory implements MongodbConnectionUriFactoryInterface
{
    private string $uri;

    public function __construct()
    {
        $host = $_ENV['MONGO_HOST'];
        $user = $_ENV['MONGO_ADMIN_USERNAME'];
        $pass = $_ENV['MONGO_ADMIN_PASSWORD'];
        $port = $_ENV['MONGO_PORT'];

        $this->uri = "mongodb://$user:$pass@$host:$port";
    }

    public function get(string $name = 'default'): string
    {
        return $this->uri;
    }
}

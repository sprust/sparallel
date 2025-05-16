<?php

declare(strict_types=1);

use Spiral\Goridge;

require_once __DIR__ . '/../vendor/autoload.php';

$rpc = new Goridge\RPC\RPC(
    Goridge\Relay::create('tcp://localhost:18077')
);

$response = $rpc->call("Server.AddTask", [
    'GroupUuid'   => uniqid(),
    'UnixTimeout' => time() + 5,
    'Payload'     => 'Hello from SParallel',
]);

print_r($response);

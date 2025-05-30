<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

pcntl_async_signals(true);

$stop = false;

pcntl_signal(SIGTERM, function () use (&$stop) {
    $stop = true;

    echo "received SIGTERM. Exit...\n";
});

while (!$stop) {
    echo 'ping: ' . time() . PHP_EOL;

    sleep(10);
}

<?php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

while (true) {
    echo 'ping: ' . time() . PHP_EOL;

    sleep(10);
}

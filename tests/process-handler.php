<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

try {
    $closure = \Opis\Closure\unserialize(
        $_SERVER[\SParallel\Drivers\Process\ProcessDriver::VARIABLE_NAME]
    );

    fwrite(STDOUT, \Opis\Closure\serialize($closure()));

    exit(0);
} catch (Throwable $exception) {
    fwrite(STDERR, \Opis\Closure\serialize($exception->getMessage()));

    exit(1);
}

<?php

declare(strict_types=1);

use SParallel\Flows\ASync\Process\Process;

require_once __DIR__ . '/../vendor/autoload.php';

$c = 50;

/** @var Process[] $processes */
$processes = [];

while ($c--) {
    $process = new Process('php ' . __DIR__ . '/scripts/pipes-test-handler.php');

    $processes[$c] = $process->start();

    $process->write("ping: $c");
}

while (count($processes)) {
    $keys = array_keys($processes);

    foreach ($keys as $key) {
        $process = $processes[$key];

        $response = '';

        while (true) {
            $chunk = $process->read(1024);

            if ($chunk === false) {
                break;
            }

            $response .= $chunk;
        }

        if (!$response) {
            continue;
        }

        echo "$key: len: " . strlen($response) . "\n";

        unset($processes[$key]);
    }
}


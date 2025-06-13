<?php

declare(strict_types=1);

use SParallel\Server\ServerBinLoader;

require_once __DIR__ . '/../../vendor/autoload.php';

$loader = new ServerBinLoader(
    __DIR__ . '/../storage/bin/sparallel_server',
);

echo "Downloading server bin [{$loader->getVersion()}]\n";

$start = microtime(true);

$loader->load();

$totalTime = microtime(true) - $start;

$fileExists = $loader->fileExists();

echo "\nSaved:\t\t" . ($fileExists ? 'true' : 'false') . "\n";
echo "TotalTime:\t$totalTime\n";

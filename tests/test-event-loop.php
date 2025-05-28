<?php

declare(strict_types=1);

use SParallel\Entities\Context;
use SParallel\SParallelThreads;

require_once __DIR__ . '/../vendor/autoload.php';

$total = (int) $_SERVER['argv'][1];

$callbacks = [];

while ($total--) {
    $callbacks[] = static function (Context $context) use ($total) {
        echo "--> $total: start\n";

        $x = 3;

        while ($x--) {
            $context->check();

            echo "--> $total: $x suspend before\n";

            SParallelThreads::continue();

            echo "--> $total: $x suspend after\n";
        }

        echo "--> $total: resume\n";
    };
}

$treads = new SParallelThreads($callbacks);

foreach ($treads->run() as $key => $result) {
    if ($result->exception) {
        echo "$key ERROR: {$result->exception->getMessage()}\n";

        continue;
    }

    echo "$key SUCCESS:\n";
    print_r($result);
}

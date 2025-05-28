<?php

declare(strict_types=1);

use SParallel\Entities\Context;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$total = (int) $_SERVER['argv'][1];

$callbacks = [];

while ($total--) {
    $callbacks["thread-$total"] = static function (Context $context) use ($total) {
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

$treads = TestContainer::resolve()->get(SParallelThreads::class);

foreach ($treads->run($callbacks) as $key => $result) {
    echo "$key SUCCESS:\n";
    print_r($result);
}

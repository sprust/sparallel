<?php

declare(strict_types=1);

use SParallel\Entities\Context;
use SParallel\SParallelThreads;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$total             = (int) ($_SERVER['argv'][1] ?? 5);
$threadsLimitCount = (int) ($_SERVER['argv'][2] ?? 0);

$counter = $total;

$start = microtime(true);

$callbacks = [];

while ($counter--) {
    $callbacks["thread-$counter"] = static function (Context $context) use ($counter) {
        echo "--> $counter: start\n";

        $x = 3;

        while ($x--) {
            $context->check();

            echo "--> $counter: $x suspend before\n";

            SParallelThreads::continue();

            echo "--> $counter: $x suspend after\n";
        }

        echo "--> $counter: resume\n";
    };
}

$treads = TestContainer::resolve()->get(SParallelThreads::class);

foreach ($treads->run($callbacks, $threadsLimitCount) as $key => $result) {
    echo "$key SUCCESS:\n";
    print_r($result);
}

$totalTime = microtime(true) - $start;
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

echo "\n\nTotal call:\t$total\n";
echo "Thr limit:\t$threadsLimitCount\n";
echo "Mem peak:\t$memPeak\n";
echo "Total time:\t$totalTime\n";

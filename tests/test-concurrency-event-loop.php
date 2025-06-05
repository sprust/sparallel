<?php

declare(strict_types=1);

use SParallel\Entities\Context;
use SParallel\SParallelConcurrency;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$total      = (int) ($_SERVER['argv'][1] ?? 5);
$limitCount = (int) ($_SERVER['argv'][2] ?? 0);

$counter = $total;

$start = microtime(true);

$callbacks = [];

while ($counter--) {
    $callbacks["conc-$counter"] = static function (Context $context) use ($counter) {
        echo "--> $counter: start\n";

        $x = 3;

        while ($x--) {
            $context->check();

            echo "--> $counter: $x suspend before\n";

            SParallelConcurrency::continue();

            echo "--> $counter: $x suspend after\n";
        }

        echo "--> $counter: resume\n";
    };
}

$concurrency = TestContainer::resolve()->get(SParallelConcurrency::class);

foreach ($concurrency->run($callbacks, $limitCount) as $key => $result) {
    echo "$key SUCCESS:\n";
    print_r($result);
}

$totalTime = microtime(true) - $start;
$memPeak   = round(memory_get_peak_usage(true) / 1024 / 1024, 4);

echo "\n\nTotal call:\t$total\n";
echo "Thr limit:\t$limitCount\n";
echo "Mem peak:\t$memPeak\n";
echo "Total time:\t$totalTime\n";

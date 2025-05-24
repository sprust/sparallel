<?php

declare(strict_types=1);

use SParallel\Server\Proxy\Mongodb\Operations\RunningOperation;
use SParallel\Server\Proxy\Mongodb\ProxyMongodbRpcClient;
use SParallel\TestsImplementation\TestContainer;

require_once __DIR__ . '/../vendor/autoload.php';

$rpc = TestContainer::resolve()->get(ProxyMongodbRpcClient::class);

$total = (int) $_SERVER['argv'][1];

$x = $total;

/** @var RunningOperation[] $operations */
$operations = [];

$start = microtime(true);

while ($x--) {
    $operation = $rpc->insertOne(
        connection: "mongodb://pms_admin:_pms_password_567@host.docker.internal:27078",
        database: 'sparallel-test',
        collection: 'test',
        document: [
            'uniq'      => uniqid(),
            'bool'      => true,
            'date'      => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
            'dates'     => [
                (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                'dates'     => [
                    (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                    (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                ],
                'dates_ass' => [
                    'one' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                    'two' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                ],
            ],
            'dates_ass' => [
                'one' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                'two' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                'dates'     => [
                    (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                    (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                ],
                'dates_ass' => [
                    'one' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                    'two' => (new DateTime())->format(DateTime::RFC3339_EXTENDED),
                ],
            ],
        ]
    );

    $operations[] = $operation;
}

$startWaiting = microtime(true);

while (count($operations) > 0) {
    usleep(100);

    $operationKeys = array_keys($operations);

    foreach ($operationKeys as $operationKey) {
        $operation = $operations[$operationKey];

        if ($operation->error) {
            echo "op error: $operation->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        $result = $rpc->insertOneResult($operation->uuid);

        if (!$result->isFinished) {
            continue;
        }

        if ($result->error) {
            echo "res error: $result->error\n";

            unset($operations[$operationKey]);

            continue;
        }

        unset($operations[$operationKey]);

        echo "success:\n" . json_encode($result->result, JSON_PRETTY_PRINT) . "\n";
    }
}

$waitingTime = microtime(true) - $startWaiting;
$totalTime   = microtime(true) - $start;

echo "\n\nWaitingTime:\t$waitingTime\nTotalTime:\t$totalTime\n";

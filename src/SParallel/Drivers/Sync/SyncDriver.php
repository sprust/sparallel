<?php

declare(strict_types=1);

namespace SParallel\Drivers\Sync;

use SParallel\Objects\ResultObject;
use SParallel\Objects\ResultsObject;
use SParallel\Contracts\DriverInterface;
use Throwable;

class SyncDriver implements DriverInterface
{
    public function run(array $callbacks): ResultsObject
    {
        $results = new ResultsObject();

        foreach ($callbacks as $key => $callback) {
            try {
                $result = new ResultObject(
                    result: $callback()
                );
            } catch (Throwable $exception) {
                $result = new ResultObject(
                    exception: $exception
                );
            }

            $results->addResult(
                key: $key,
                result: $result
            );
        }

        $results->finish();

        return $results;
    }
}

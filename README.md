# Parallel PHP

## example ##

```php
try {
    $driverFactory = new \SParallel\Drivers\Factory(
        container: \SParallel\Tests\Container::resolve()
    );
    
    /** @var \SParallel\Objects\ResultsObject $results */
    $results = (new \SParallel\Services\SParallelService($driverFactory->detect()))->wait(
        callbacks: [
            'first'  => static fn() => 'first',
            'second' => static fn() => 'second',
        ],
        waitMicroseconds: 2_000_000, // 2 seconds
    );
} catch (\SParallel\Exceptions\SParallelTimeoutException) {
    throw new RuntimeException('Timeout');
}

if ($results->hasFailed()) {
    foreach ($results->getFailed() as $key => $failedResult) {
        echo sprintf(
            'Failed task: %s\n%s\n',
            $key, $failedResult->error?->message ?? 'unknown error'
        );
    }
}

foreach ($results->getResults() as $result) {
    if ($failedResult->error) {
        continue;
    }

    echo $result->result . "\n";
}
```

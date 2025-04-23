# Parallel PHP via processes (another)

## example ##

```php
try {
    $driverFactory = new \SParallel\Drivers\Factory(
        container: \SParallel\Tests\Container::resolve()
    );
    
    $results = (new \SParallel\Services\SParallelService($driverFactory->detect()))->run(
        callbacks: [
            'first'  => static fn() => 'first',
            'second' => static fn() => 'second',
        ],
        timeoutSeconds: 2,
    );
} catch (\SParallel\Exceptions\SParallelTimeoutException) {
    throw new RuntimeException('Timeout');
}

foreach ($results as $taskKey => $result) {
    if ($result->error) {
        echo "$taskKey: ERROR: " . ($result->error?->message ?: 'unknown error') . "\n";
        
        continue;
    }

    echo "$taskKey: SUCCESS: " . $result->result . "\n";
}
```

# Parallel PHP via processes (another)

<summary>⚠️ Don't use a writing to STDOUT inside workers</summary>
<summary>⚠️ VarDumper is muted inside workers</summary>

## example ##

Init

```php
$workers = \SParallel\TestsImplementation\TestContainer::resolve()
    ->get(\SParallel\SParallelWorkers::class);

$callbacks = [
    'first'  => static fn() => 'first',
    'second' => static fn() => throw new RuntimeException('second'),
     'third'  => static function(
        \SParallel\Entities\Context $context,
        \SParallel\Contracts\EventsBusInterface $eventsBus // DI support
    ) {
        $context->check();
        
        return 'third';
    },
];
```

Wait all tasks to finish and get results

```php
/** 
 * @var \SParallel\SParallelWorkers $workers 
 * @var array<string, Closure> $callbacks 
 */

$results = $workers->wait(
    callbacks: $callbacks,
    timeoutSeconds: 2,
);

if ($results->hasFailed()) {
    foreach ($results->getFailed() as $key => $failedResult) {
        echo "$taskKey: ERROR: " . ($failedResult->exception?->getMessage() ?: 'unknown error') . "\n";
    }
}

foreach ($results->getResults() as $result) {
    if ($result->exception) {
        continue;
    }

    echo "$taskKey: SUCCESS: " . $result->result . "\n";
}
```

Run tasks and get results at any task completion

```php
/** 
 * @var \SParallel\SParallelWorkers $workers 
 * @var array<string, Closure> $callbacks 
 */

$results = $workers->run(
    callbacks: $callbacks,
    timeoutSeconds: 2,
);

foreach ($results as $taskKey => $result) {
    if ($result->exception) {
        echo "$taskKey: ERROR: " . ($result->exception->getMessage() ?: 'unknown error') . "\n";
        
        continue;
    }

    echo "$taskKey: SUCCESS: " . $result->result . "\n";
}
```

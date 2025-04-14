<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use RuntimeException;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Objects\ResultObject;
use SParallel\Objects\ResultsObject;
use Throwable;

class ForkWaitGroup implements WaitGroupInterface
{
    protected ResultsObject $results;

    /**
     * @param array<mixed, Task> $tasks
     */
    public function __construct(
        protected array $tasks,
    ) {
        $this->results = new ResultsObject();
    }

    public function current(): ResultsObject
    {
        $keys = array_keys($this->tasks);

        foreach ($keys as $key) {
            $task = $this->tasks[$key];

            if (!$task->isFinished()) {
                continue;
            }

            $output = $task->output();

            $outputData = json_decode($output, true);

            if (!is_array($outputData)) {
                $this->results->addResult(
                    key: $key,
                    result: new ResultObject(
                        exception: new RuntimeException(
                            "Failed to decode JSON for task [$key]"
                        )
                    )
                );
            } else {
                $data = $outputData['data'];

                if ($outputData['success'] === true) {
                    $this->results->addResult(
                        key: $key,
                        result: new ResultObject(
                            result: \Opis\Closure\unserialize($data)
                        )
                    );
                } else {
                    $this->results->addResult(
                        key: $key,
                        result: new ResultObject(
                            exception: new RuntimeException(
                                message: $data ? \Opis\Closure\unserialize($data) : 'Unknown error',
                            )
                        )
                    );
                }
            }


            unset($this->tasks[$key]);
        }

        return $this->results;
    }

    public function break(): void
    {
        $keys = array_keys($this->tasks);

        foreach ($keys as $key) {
            $task = $this->tasks[$key];

            if (!$task->isFinished()) {
                try {
                    posix_kill($task->getPid(), SIGKILL);
                } catch (Throwable) {
                    //
                }
            }

            unset($this->tasks[$key]);
        }
    }
}

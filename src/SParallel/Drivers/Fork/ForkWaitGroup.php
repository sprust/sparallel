<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork;

use SParallel\Contracts\WaitGroupInterface;
use SParallel\Drivers\Fork\Service\Task;
use SParallel\Objects\ResultsObject;
use SParallel\Transport\ResultTransport;
use Throwable;

class ForkWaitGroup implements WaitGroupInterface
{
    protected ResultsObject $results;

    /**
     * @param array<mixed, Task> $tasks
     */
    public function __construct(
        protected ResultTransport $resultTransport,
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

            $this->results->addResult(
                key: $key,
                result: $this->resultTransport->unserialize($output),
            );

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

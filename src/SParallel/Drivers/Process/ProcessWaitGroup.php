<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ResultsObject;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessWaitGroup implements WaitGroupInterface
{
    protected ResultsObject $results;

    /**
     * @param array<mixed, Process> $processes
     */
    public function __construct(
        protected ProcessConnectionInterface $connection,
        protected ResultTransport $resultTransport,
        protected array $processes,
    ) {
        $this->results = new ResultsObject();
    }

    public function current(): ResultsObject
    {
        $keys = array_keys($this->processes);

        foreach ($keys as $key) {
            $process = $this->processes[$key];

            if ($process->isRunning()) {
                continue;
            }

            $output = $this->connection->read($process);

            $this->results->addResult(
                key: $key,
                result: $this->resultTransport->unserialize($output),
            );

            unset($this->processes[$key]);
        }

        return $this->results;
    }

    public function break(): void
    {
        $keys = array_keys($this->processes);

        foreach ($keys as $key) {
            $process = $this->processes[$key];

            if ($process->isRunning()) {
                try {
                    $process->stop();
                } catch (Throwable) {
                    //
                }
            }

            unset($this->processes[$key]);
        }
    }
}

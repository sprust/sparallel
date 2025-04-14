<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ResultsObject;
use SParallel\Transport\TaskResultTransport;
use Symfony\Component\Process\Process;
use Throwable;

class ProcessWaitGroup implements WaitGroupInterface
{
    protected ResultsObject $results;

    /**
     * @param array<mixed, Process> $processes
     */
    public function __construct(
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

            $output = $this->getOutput($process);

            $this->results->addResult(
                key: $key,
                result: TaskResultTransport::unSerialize($output),
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

    private function getOutput(Process $process): ?string
    {
        if ($output = $process->getOutput()) {
            return $output;
        }

        if ($errorOutput = $process->getErrorOutput()) {
            return $errorOutput;
        }

        return null;
    }
}

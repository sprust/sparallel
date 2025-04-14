<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Objects\ResultObject;
use SParallel\Objects\ResultsObject;
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

            if ($process->isSuccessful()) {
                $output = $process->getOutput();

                $this->results->addResult(
                    key: $key,
                    result: new ResultObject(
                        result: $output ? \Opis\Closure\unserialize($output) : null,
                    )
                );
            } else {
                $this->results->addResult(
                    key: $key,
                    result: new ResultObject(
                        exception: new RuntimeException(
                            message: $process->getErrorOutput() ?: 'Unknown error',
                        )
                    )
                );
            }

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

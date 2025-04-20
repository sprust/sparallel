<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use Generator;
use SParallel\Contracts\EventsBusInterface;
use SParallel\Contracts\ProcessConnectionInterface;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Transport\ResultTransport;
use Symfony\Component\Process\Process;

class ProcessWaitGroup implements WaitGroupInterface
{
    /**
     * @param array<mixed, Process> $processes
     */
    public function __construct(
        protected array $processes,
        protected ProcessConnectionInterface $connection,
        protected ResultTransport $resultTransport,
        protected EventsBusInterface $eventsBus,
    ) {
    }

    public function get(): Generator
    {
        while (count($this->processes) > 0) {
            $keys = array_keys($this->processes);

            foreach ($keys as $key) {
                $process = $this->processes[$key];

                if ($process->isRunning()) {
                    continue;
                }

                if ($pid = $process->getPid()) {
                    $this->eventsBus->processFinished($pid);
                }

                $output = $this->connection->read($process);

                unset($this->processes[$key]);

                yield $this->resultTransport->unserialize($output);
            }
        }
    }

    public function break(): void
    {
        $keys = array_keys($this->processes);

        foreach ($keys as $key) {
            $process = $this->processes[$key];

            if ($process->isRunning()) {
                if ($pid = $process->getPid()) {
                    $this->eventsBus->processFinished($pid);
                }

                $process->stop();
            }

            unset($this->processes[$key]);
        }
    }

    public function __destruct()
    {
        $this->break();
    }
}

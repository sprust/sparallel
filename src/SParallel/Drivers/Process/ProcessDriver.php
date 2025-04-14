<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use SParallel\Contracts\WaitGroupInterface;
use SParallel\Contracts\DriverInterface;
use Symfony\Component\Process\PhpExecutableFinder;
use Symfony\Component\Process\Process;

class ProcessDriver implements DriverInterface
{
    public const VARIABLE_NAME = 'S_PARALLEL_SERIALIZED_CALLBACK';

    public function __construct(protected string $scriptPath)
    {
    }

    public function wait(array $callbacks): WaitGroupInterface
    {
        $processes = [];

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        foreach ($callbacks as $key => $callback) {
            $process = Process::fromShellCommandline(command: $command)
                ->setTimeout(null)
                ->setEnv([
                    static::VARIABLE_NAME => \Opis\Closure\serialize($callback),
                ]);

            $process->start();

            $processes[$key] = $process;
        }

        return new ProcessWaitGroup(
            processes: $processes,
        );
    }
}

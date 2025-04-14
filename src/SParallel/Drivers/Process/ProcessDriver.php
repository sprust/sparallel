<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
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
        $this->checkScriptPath();

        $processes = [];

        $command = sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            $this->scriptPath,
        );

        $workDir = dirname($this->scriptPath);

        foreach ($callbacks as $key => $callback) {
            $process = Process::fromShellCommandline(command: $command, cwd: $workDir)
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

    private function checkScriptPath(): void
    {
        if (!file_exists($this->scriptPath)) {
            throw new RuntimeException(
                message: sprintf(
                    'Script path [%s] does not exist.',
                    $this->scriptPath,
                )
            );
        }
    }
}

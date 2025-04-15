<?php

declare(strict_types=1);

namespace SParallel\Drivers\Process;

use RuntimeException;
use SParallel\Contracts\WaitGroupInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Transport\Serializer;
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

        foreach ($callbacks as $key => $callback) {
            $process = Process::fromShellCommandline(command: $command)
                ->setTimeout(null)
                ->setEnv([
                    static::VARIABLE_NAME => Serializer::serialize($callback),
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
        $scriptPath = explode(' ', $this->scriptPath)[0];

        if (!file_exists($scriptPath)) {
            throw new RuntimeException(
                message: sprintf(
                    'Script path [%s] does not exist.',
                    $scriptPath,
                )
            );
        }
    }
}

<?php

namespace SParallel\Services\Process;

use Closure;
use Symfony\Component\Process\Process;

class ProcessService
{
    public function getOutput(Process $process): ?string
    {
        if (!$process->isStarted()) {
            return null;
        }

        if ($output = $process->getOutput()) {
            $process->clearOutput();

            return trim($output);
        }

        if ($errorOutput = $process->getErrorOutput()) {
            $process->clearErrorOutput();

            return trim($errorOutput);
        }

        return null;
    }

    public function registerShutdownFunction(Closure $callback): void
    {
        register_shutdown_function($callback);
    }
}

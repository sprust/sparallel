<?php

namespace SParallel\Services\Process;

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

            return $output;
        }

        if ($errorOutput = $process->getErrorOutput()) {
            $process->clearErrorOutput();

            return $errorOutput;
        }

        return null;
    }
}

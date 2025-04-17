<?php

namespace SParallel\Drivers\Process\Service;

use SParallel\Contracts\ProcessConnectionInterface;
use Symfony\Component\Process\Process;

class ProcessConnection implements ProcessConnectionInterface
{
    public function out(string $data, bool $isError): void
    {
        fwrite($isError ? STDERR : STDOUT, $data);
    }

    public function read(Process $process): ?string
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

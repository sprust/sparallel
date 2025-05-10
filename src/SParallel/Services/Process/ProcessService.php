<?php

declare(strict_types=1);

namespace SParallel\Services\Process;

use Psr\Log\LoggerInterface;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;
use Symfony\Component\Process\Process;

class ProcessService
{
    public function __construct(protected LoggerInterface $logger)
    {
    }

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

    /**
     * @throws ContextCheckerException
     */
    public function killChildren(Context $context, string $caller, int $pid): void
    {
        $command = sprintf('ps -o pid,ppid -A | awk \'$2 == %d { print $1 }\'', $pid);

        exec($command, $output);

        $childPids = array_map('intval', $output);

        foreach ($childPids as $childPid) {
            posix_kill($childPid, SIGTERM);

            $this->logger->debug(
                sprintf(
                    "%s kills child process [pPid: %s, cPid: %s]",
                    $caller,
                    $pid,
                    $childPid,
                )
            );
        }

        $childPids = array_flip($childPids);

        while (count($childPids) > 0) {
            $context->check();

            $childPidKeys = array_keys($childPids);

            foreach ($childPidKeys as $childPidKey) {
                if (posix_kill($childPidKey, 0)) {
                    continue;
                }

                unset($childPids[$childPidKey]);
            }

            usleep(100);
        }
    }

    public function printOutput(Process $process): void
    {
        if ($output = $this->getOutput($process)) {
            echo "PROCESS OUTPUT {$process->getPid()}:\n$output\n";
        }
    }
}

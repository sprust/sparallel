<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use RuntimeException;

class ProcessRunner
{
    protected mixed $process = null;
    protected array $pipes = [];

    public function __construct(protected string $command)
    {
    }

    public function startProcess(): void
    {
        $descriptorSpec = [
            0 => ["pipe", "r"], // stdin
            1 => ["pipe", "w"], // stdout
            2 => ["pipe", "w"], // stderr
        ];

        $this->process = proc_open($this->command, $descriptorSpec, $this->pipes);

        if (!is_resource($this->process)) {
            throw new RuntimeException(
                "Can't start process [$this->command]."
            );
        }

        stream_set_blocking($this->pipes[1], false);
        stream_set_blocking($this->pipes[2], false);
    }

    public function writeInput(string $input): void
    {
        if (!$this->process) {
            throw new RuntimeException(
                "Process is not started."
            );
        }

        fwrite($this->pipes[0], $input);
    }

    public function readOutput(): ?string
    {
        return stream_get_contents($this->pipes[1]);
    }

    public function readError(): ?string
    {
        return stream_get_contents($this->pipes[2]);
    }

    public function isRunning(): bool
    {
        return !$this->isFinished();
    }

    public function isFinished(): bool
    {
        if (!$this->process) {
            return true;
        }

        $status = proc_get_status($this->process);

        return !$status['running'];
    }

    public function close(): void
    {
        if ($this->process) {
            foreach ($this->pipes as $pipe) {
                fclose($pipe);
            }

            proc_close($this->process);
        }
    }

    public function __destruct()
    {
        $this->close();
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Flows\ASync\Process;

use LogicException;
use SParallel\Exceptions\CantCreateProcessException;
use SParallel\Exceptions\ContextCheckerException;
use SParallel\Services\Context;

class Process
{
    private string $uuid;

    private int $pid = -1;

    /**
     * @var resource|null
     */
    private mixed $process = null;

    /**
     * @var array{0: resource, 1: resource, 3: resource}|null
     */
    private ?array $pipes = null;

    public function __construct(private readonly string $command)
    {
        $this->uuid = uniqid(more_entropy: true);;
    }

    public function getUuid(): string
    {
        return $this->uuid;
    }

    public function getPid(): int
    {
        return $this->pid;
    }

    public function start(): static
    {
        $descriptorSpec = [
            0 => ['pipe', 'r'], // stdin
            1 => ['pipe', 'w'], // stdout
            2 => ['pipe', 'w'], // stderr
        ];

        $this->process = proc_open($this->command, $descriptorSpec, $pipes);

        if (!is_resource($this->process)) {
            throw new CantCreateProcessException(
                command: $this->command
            );
        }

        foreach ($pipes as $pipe) {
            stream_set_blocking($pipe, false);
        }

        $this->pid   = proc_get_status($this->process)['pid'];
        $this->pipes = $pipes;

        return $this;
    }

    public function write(string $data): void
    {
        //fwrite($this->pipes[0], $data);

        fwrite($this->pipes[0], pack('N', strlen($data)));
        fwrite($this->pipes[0], $data);
    }

    public function read(int $chunkSize = 1024): string|false
    {
        $stream = $this->pipes[1];

        $lenPacked = fread($stream, 4);

        if ($lenPacked === false) {
            return false;
        }

        if (strlen($lenPacked) !== 4) {
            return false;
        }

        $len = unpack('N', $lenPacked)[1];

        $response = '';

        while (strlen($response) < $len) {
            $response .= fread($stream, $len - strlen($response));
        }

        return $response;

        //$response = '';
        //
        //while (!feof($this->pipes[1])) {
        //    $chunk = fgets($this->pipes[1], $chunkSize);
        //
        //    if ($chunk === false) {
        //        break;
        //    }
        //
        //    $response .= $chunk;
        //}
        //
        //if ($response === '') {
        //    return false;
        //}
        //
        //return $response;
    }

    /**
     * @throws ContextCheckerException
     */
    public function wait(Context $context): void
    {
        while (!$this->isRunning()) {
            $context->check();

            usleep(100);
        }
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

    public function stop(): int
    {
        $this->pid = -1;

        foreach ($this->pipes ?? [] as $pipe) {
            fclose($pipe);
        }

        $this->pipes = null;

        if (is_resource($this->process)) {
            $exitCode = proc_close($this->process);

            $this->process = null;

            return $exitCode;
        }

        return -1;
    }

    public function __destruct()
    {
        $this->stop();
    }
}

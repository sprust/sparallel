<?php

namespace SParallel\Contracts;

use Symfony\Component\Process\Process;

interface ProcessConnectionInterface
{
    public function out(string $data, bool $isError): void;

    public function read(Process $process): ?string;
}

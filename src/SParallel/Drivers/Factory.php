<?php

declare(strict_types=1);

namespace SParallel\Drivers;

use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\FactoryInterface;

class Factory implements FactoryInterface
{
    public function __construct(
        protected ?bool $isRunningInConsole = null,
        protected ?DriverInterface $driver = null,
    ) {
    }

    public function detect(): DriverInterface
    {
        if (!is_null($this->driver)) {
            return $this->driver;
        }

        return $this->driver = $this->runningInConsole()
            ? new ForkDriver()
            : new ProcessDriver();
    }

    private function runningInConsole(): bool
    {
        if (!is_null($this->isRunningInConsole)) {
            return $this->isRunningInConsole;
        }

        return $this->isRunningInConsole = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    }
}

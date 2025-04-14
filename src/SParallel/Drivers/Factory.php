<?php

declare(strict_types=1);

namespace SParallel\Drivers;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Drivers\Fork\ForkDriver;
use SParallel\Drivers\Process\ProcessDriver;
use SParallel\Contracts\DriverInterface;
use SParallel\Contracts\FactoryInterface;

class Factory implements FactoryInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected ?bool $isRunningInConsole = null,
        protected ?DriverInterface $driver = null,
    ) {
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function detect(): DriverInterface
    {
        if (!is_null($this->driver)) {
            return $this->driver;
        }

        return $this->driver = $this->runningInConsole()
            ? $this->container->get(ForkDriver::class)
            : $this->container->get(ProcessDriver::class);
    }

    private function runningInConsole(): bool
    {
        if (!is_null($this->isRunningInConsole)) {
            return $this->isRunningInConsole;
        }

        return $this->isRunningInConsole = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    }
}

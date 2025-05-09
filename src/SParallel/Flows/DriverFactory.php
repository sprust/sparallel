<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Flows\ASync\Fork\ForkDriver;
use SParallel\Flows\ASync\Process\ProcessDriver;

class DriverFactory implements DriverFactoryInterface
{
    public function __construct(
        protected ContainerInterface $container,
        protected ?bool $isRunningInConsole = null,
        protected ?DriverInterface $driver = null,
    ) {
    }

    public function forceDriver(?DriverInterface $driver): void
    {
        $this->driver = $driver;
    }

    /**
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     */
    public function detect(): DriverInterface
    {
        if ($this->driver) {
            return $this->driver;
        }

        if ($this->runningInConsole()) {
            $driver = $this->container->get(ForkDriver::class);
        } else {
            $driver = $this->container->get(ProcessDriver::class);
        }

        return $this->driver = $driver;
    }

    private function runningInConsole(): bool
    {
        if (!is_null($this->isRunningInConsole)) {
            return $this->isRunningInConsole;
        }

        return $this->isRunningInConsole = (PHP_SAPI === 'cli' || PHP_SAPI === 'phpdbg');
    }
}

<?php

declare(strict_types=1);

namespace SParallel\Flows;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use SParallel\Contracts\DriverFactoryInterface;
use SParallel\Contracts\DriverInterface;
use SParallel\Drivers\Sync\SyncDriver;

class DriverFactory implements DriverFactoryInterface
{
    public function __construct(
        protected ContainerInterface $container,
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

        return $this->driver = $this->container->get(SyncDriver::class);
    }
}

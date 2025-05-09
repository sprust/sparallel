<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface DriverFactoryInterface
{
    public function forceDriver(?DriverInterface $driver): void;

    public function detect(): DriverInterface;
}

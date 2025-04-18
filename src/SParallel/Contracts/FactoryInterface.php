<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface FactoryInterface
{
    public function detect(): DriverInterface;
}

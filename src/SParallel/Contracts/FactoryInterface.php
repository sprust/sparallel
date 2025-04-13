<?php

namespace SParallel\Contracts;

interface FactoryInterface
{
    public function detect(): DriverInterface;
}

<?php

declare(strict_types=1);

namespace SParallel\Contracts;

use Psr\Container\ContainerInterface;

interface ContainerFactoryInterface
{
    public function create(): ContainerInterface;
}

<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use Psr\Container\ContainerInterface;
use SParallel\Contracts\ContainerFactoryInterface;

readonly class TestContainerFactory implements ContainerFactoryInterface
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function create(): ContainerInterface
    {
        return $this->container;
    }
}

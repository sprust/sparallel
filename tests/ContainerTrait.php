<?php

namespace SParallel\Tests;

use Psr\Container\ContainerInterface;

trait ContainerTrait
{
    protected static function getContainer(): ContainerInterface
    {
        return new Container();
    }
}

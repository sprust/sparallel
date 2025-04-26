<?php

namespace SParallel\Tests;

use SParallel\Contracts\ContextSetterInterface;
use SParallel\Services\Context;

class ContextSetter implements ContextSetterInterface
{
    public function set(Context $context): void
    {
        TestContainer::resolve()->set(Context::class, static fn() => $context);
    }
}

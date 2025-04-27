<?php

namespace SParallel\Tests;

use SParallel\Contracts\ContextResolverInterface;
use SParallel\Services\Context;

class ContextResolver implements ContextResolverInterface
{
    public function set(Context $context): void
    {
        TestContainer::resolve()->set(Context::class, static fn() => $context);
    }

    public function get(): Context
    {
        return TestContainer::resolve()->get(Context::class);
    }
}

<?php

declare(strict_types=1);

namespace SParallel\TestsImplementation;

use SParallel\Contracts\FlowTypeResolverInterface;

class TestFlowTypeResolver implements FlowTypeResolverInterface
{
    public static bool $isAsync = true;

    public function isAsync(): bool
    {
        return self::$isAsync;
    }
}

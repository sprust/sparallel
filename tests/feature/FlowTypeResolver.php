<?php

declare(strict_types=1);

namespace SParallel\Tests;

use SParallel\Contracts\FlowTypeResolverInterface;

class FlowTypeResolver implements FlowTypeResolverInterface
{
    public static bool $isAsync = true;

    public function isAsync(): bool
    {
        return self::$isAsync;
    }
}

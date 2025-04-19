<?php

namespace SParallel\Tests;

use SParallel\Contracts\ASyncScriptPathResolverInterface;

class ASyncScriptPathResolver implements ASyncScriptPathResolverInterface
{
    public function get(): string
    {
        return __DIR__ . '/../scripts/test-process-async-handler.php' . ' param1 param2';
    }
}

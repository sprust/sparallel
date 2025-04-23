<?php

namespace SParallel\Tests;

use SParallel\Contracts\ProcessScriptPathResolverInterface;

class ProcessScriptPathResolver implements ProcessScriptPathResolverInterface
{
    public function get(): string
    {
        return __DIR__ . '/../../scripts/test-process-handler.php' . ' param1 param2';
    }
}

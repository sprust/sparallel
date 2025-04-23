<?php

namespace SParallel\Tests;

use SParallel\Contracts\HybridScriptPathResolverInterface;

class HybridScriptPathResolver implements HybridScriptPathResolverInterface
{
    public function get(): string
    {
        return __DIR__ . '/../../scripts/test-process-hybrid-handler.php' . ' param1 param2';
    }
}

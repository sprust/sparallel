<?php

namespace SParallel\Tests;

use SParallel\Contracts\HybridProcessCommandResolverInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class HybridProcessCommandResolver implements HybridProcessCommandResolverInterface
{
    public function get(): string
    {
        return sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            __DIR__ . '/../scripts/hybrid-process-handler.php' . ' param1 param2',
        );
    }
}

<?php

namespace SParallel\TestsImplementation;

use SParallel\Contracts\HybridProcessCommandResolverInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class TestHybridProcessCommandResolver implements HybridProcessCommandResolverInterface
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

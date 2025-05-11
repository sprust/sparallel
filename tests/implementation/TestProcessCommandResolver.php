<?php

namespace SParallel\TestsImplementation;

use SParallel\Contracts\ProcessCommandResolverInterface;
use Symfony\Component\Process\PhpExecutableFinder;

class TestProcessCommandResolver implements ProcessCommandResolverInterface
{
    public function get(): string
    {
        return sprintf(
            '%s %s',
            (new PhpExecutableFinder())->find(),
            __DIR__ . '/../scripts/process-handler.php' . ' param1 param2',
        );
    }
}

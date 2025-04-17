<?php

namespace SParallel\Contracts;

interface ProcessScriptPathResolverInterface
{
    public function get(): string;
}

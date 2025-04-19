<?php

namespace SParallel\Contracts;

interface ASyncScriptPathResolverInterface
{
    public function get(): string;
}

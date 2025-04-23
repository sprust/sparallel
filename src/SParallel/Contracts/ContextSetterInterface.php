<?php

namespace SParallel\Contracts;

use SParallel\Objects\Context;

interface ContextSetterInterface
{
    public function set(Context $context): void;
}

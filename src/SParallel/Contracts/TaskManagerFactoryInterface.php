<?php

declare(strict_types=1);

namespace SParallel\Contracts;

interface TaskManagerFactoryInterface
{
    public function forceDriver(?TaskManagerInterface $taskManager): void;

    public function detect(): TaskManagerInterface;
}

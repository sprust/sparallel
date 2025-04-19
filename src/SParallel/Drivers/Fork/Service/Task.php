<?php

declare(strict_types=1);

namespace SParallel\Drivers\Fork\Service;

use RuntimeException;
use SParallel\Objects\SocketServerObject;

readonly class Task
{
    public function __construct(public int $pid, public SocketServerObject $socketServer)
    {
    }
}
